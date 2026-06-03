<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\Specialist;
use App\Models\SpecialistAvailability;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RecommendationService
{
    private const NEAREST_DAY_SEARCH_WINDOW = 30;

    public function getRecommendedTimes($clientId, $specialistId, $date, ?array $serviceIds = null)
    {
        $serviceIds = $serviceIds ?? [];

        $services = Service::query()
            ->whereIn('id', $serviceIds)
            ->get();

        if (!empty($serviceIds) && $services->count() !== count(array_unique($serviceIds))) {
            return [
                'message' => 'One or more selected services do not exist.',
                'slots' => [],
                'warnings' => [],
                'alternative_day_slots' => [],
            ];
        }

        if ($services->isNotEmpty() && $services->contains(fn ($service) => (int) $service->specialist_id !== (int) $specialistId)) {
            return [
                'message' => 'All selected services must belong to the selected specialist.',
                'slots' => [],
                'warnings' => [],
                'alternative_day_slots' => [],
            ];
        }

        $duration = $services->sum('duration');

        if ($duration <= 0) {
            $duration = 30;
        }

        $slots = $this->availableSlots($specialistId, $date, $duration);
        $warnings = [];
        $alternativeDay = [];

        $dailyLoad = Appointment::where('specialist_id', $specialistId)
            ->whereDate('start_time', $date)
            ->where('status', '!=', 'CANCELED')
            ->count();

        if ($dailyLoad >= 8) {
            $warnings[] = 'Selected specialist has high load on this day.';
        }

        if (empty($slots)) {
            $warnings[] = 'No free slots found for selected day. Nearest available day is suggested.';

            return [
                'slots' => [],
                'warnings' => $warnings,
                'alternative_day_slots' => $this->findNearestAvailableDay($clientId, $specialistId, $date, $duration),
            ];
        }

        $results = $this->scoreSlots($slots, $clientId, $specialistId);

        if ($dailyLoad >= 8) {
            $alternativeDay = $this->findNearestAvailableDay($clientId, $specialistId, $date, $duration);
        }

        return [
            'slots' => $results,
            'warnings' => $warnings,
            'alternative_day_slots' => $alternativeDay,
        ];
    }

    private function availableSlots($specialistId, $date, $duration)
    {
        $availability = SpecialistAvailability::where('specialist_id', $specialistId)
            ->whereDate('date', $date)
            ->get();

        $slots = [];

        foreach ($availability as $window) {
            $start = Carbon::parse($date . ' ' . $window->start_time);
            $end = Carbon::parse($date . ' ' . $window->end_time);

            while ($start->copy()->addMinutes($duration) <= $end) {
                $slotStart = $start->copy();
                $slotEnd = $start->copy()->addMinutes($duration);

                if (!$this->hasConflict($specialistId, $slotStart, $slotEnd)) {
                    $slots[] = [
                        'start' => $slotStart->toDateTimeString(),
                        'end' => $slotEnd->toDateTimeString(),
                    ];
                }

                $start->addMinutes(30);
            }
        }

        return $slots;
    }

    private function hasConflict($specialistId, $start, $end)
    {
        return Appointment::where('specialist_id', $specialistId)
            ->where('status', '!=', 'CANCELED')
            ->where(function ($query) use ($start, $end) {
                $query->where('start_time', '<', $end)
                    ->where('end_time', '>', $start);
            })
            ->exists();
    }

    private function clientReliability($clientId)
    {
        $appointments = Appointment::where('client_id', $clientId)->get();

        if ($appointments->count() === 0) {
            return 0.70;
        }

        $noShows = $appointments->where('status', 'NO_SHOW')->count();

        return 1 - ($noShows / $appointments->count());
    }

    private function timeReliability($slot)
    {
        $hour = Carbon::parse($slot)->hour;

        if ($hour >= 9 && $hour <= 12) {
            return 0.9;
        }

        if ($hour <= 15) {
            return 0.7;
        }

        return 0.5;
    }

    private function dayReliability($slot)
    {
        $day = Carbon::parse($slot)->dayOfWeek;

        if ($day >= 1 && $day <= 5) {
            return 0.9;
        }

        return 0.6;
    }

    private function specialistLoad($specialistId, $slot)
    {
        $count = Appointment::where('specialist_id', $specialistId)
            ->whereDate('start_time', Carbon::parse($slot)->toDateString())
            ->where('status', '!=', 'CANCELED')
            ->count();

        return max(0, 1 - ($count / 10));
    }

    private function specialistCancellationRisk($specialistId, $slot)
    {
        $hour = Carbon::parse($slot)->format('H');
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $hourExpression = "strftime('%H', start_time)";
        } else {
            $hourExpression = "LPAD(HOUR(start_time), 2, '0')";
        }

        $cancelledAtHour = Appointment::where('specialist_id', $specialistId)
            ->where('status', 'CANCELED')
            ->whereRaw("$hourExpression = ?", [$hour])
            ->count();

        $totalAtHour = Appointment::where('specialist_id', $specialistId)
            ->whereRaw("$hourExpression = ?", [$hour])
            ->count();

        if ($totalAtHour === 0) {
            return 0.9;
        }

        return max(0.2, 1 - ($cancelledAtHour / $totalAtHour));
    }

    private function findNearestAvailableDay($clientId, $specialistId, $date, $duration)
    {
        for ($i = 1; $i <= self::NEAREST_DAY_SEARCH_WINDOW; $i++) {
            $candidate = Carbon::parse($date)->addDays($i)->toDateString();

            $candidateSlots = $this->availableSlots($specialistId, $candidate, $duration);

            if (!empty($candidateSlots)) {
                $dayLoad = Appointment::where('specialist_id', $specialistId)
                    ->whereDate('start_time', $candidate)
                    ->where('status', '!=', 'CANCELED')
                    ->count();

                return [
                    'date' => $candidate,
                    'day_load' => $dayLoad,
                    'slots' => $this->scoreSlots($candidateSlots, $clientId, $specialistId),
                ];
            }
        }

        return [
            'date' => null,
            'day_load' => null,
            'slots' => [],
            'message' => 'No availability found in the next ' . self::NEAREST_DAY_SEARCH_WINDOW . ' days.',
        ];
    }

    private function scoreSlots($slots, $clientId, $specialistId)
    {
        $specialistProfile = Specialist::where('user_id', $specialistId)->first();
        $workloadFactor = $specialistProfile?->workload_factor ?? 1.0;

        $results = [];

        foreach ($slots as $slot) {
            $clientScore = $this->clientReliability($clientId);
            $timeScore = $this->timeReliability($slot['start']);
            $dayScore = $this->dayReliability($slot['start']);
            $loadScore = $this->specialistLoad($specialistId, $slot['start']);
            $cancellationScore = $this->specialistCancellationRisk($specialistId, $slot['start']);

            $score =
                ($clientScore * 0.4) +
                ($timeScore * 0.3) +
                ($dayScore * 0.15) +
                ($loadScore * 0.1) +
                ($cancellationScore * 0.05);

            $results[] = [
                'start' => $slot['start'],
                'end' => $slot['end'],
                'score' => round($score * $workloadFactor, 2),
            ];
        }

        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $results;
    }
}