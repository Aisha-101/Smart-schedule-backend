<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function index()
    {
        $from = request('from');
        $to = request('to');

        $appointmentsQuery = Appointment::query();

        if ($from) {
            $appointmentsQuery->whereDate('start_time', '>=', $from);
        }

        if ($to) {
            $appointmentsQuery->whereDate('start_time', '<=', $to);
        }

        $totalAppointments = (clone $appointmentsQuery)->count();

        $scheduled = (clone $appointmentsQuery)
            ->where('status', 'SCHEDULED')
            ->count();

        $completed = (clone $appointmentsQuery)
            ->where('status', 'COMPLETED')
            ->count();

        $canceled = (clone $appointmentsQuery)
            ->where('status', 'CANCELED')
            ->count();

        $noShows = (clone $appointmentsQuery)
            ->where('status', 'NO_SHOW')
            ->count();

        $late = (clone $appointmentsQuery)
            ->where('status', 'LATE')
            ->count();

        $averageDelay = (clone $appointmentsQuery)
            ->where('status', 'LATE')
            ->avg('delay_minutes');

        $specialistsCount = User::where('role', 'SPECIALIST')->count();
        $clientsCount = User::where('role', 'CLIENT')->count();
        $servicesCount = Service::count();

        $specialistLoad = User::where('role', 'SPECIALIST')
            ->select('id', 'name', 'email')
            ->withCount([
                'specialistAppointments as appointment_count' => function ($query) use ($from, $to) {
                    if ($from) {
                        $query->whereDate('start_time', '>=', $from);
                    }

                    if ($to) {
                        $query->whereDate('start_time', '<=', $to);
                    }
                }
            ])
            ->orderByDesc('appointment_count')
            ->get();

        $busyHoursQuery = Appointment::select(
                DB::raw('HOUR(start_time) as hour'),
                DB::raw('COUNT(*) as count')
            );

        if ($from) {
            $busyHoursQuery->whereDate('start_time', '>=', $from);
        }

        if ($to) {
            $busyHoursQuery->whereDate('start_time', '<=', $to);
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $busyHours = Appointment::selectRaw("strftime('%H', start_time) as hour, COUNT(*) as count")
                ->groupByRaw("strftime('%H', start_time)")
                ->orderByDesc('count')
                ->get();
        } else {
            $busyHours = Appointment::selectRaw('HOUR(start_time) as hour, COUNT(*) as count')
                ->groupByRaw('HOUR(start_time)')
                ->orderByDesc('count')
                ->get();
        }
        return response()->json([
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],

            'summary' => [
                'total_appointments' => $totalAppointments,
                'scheduled' => $scheduled,
                'completed' => $completed,
                'canceled' => $canceled,
                'no_shows' => $noShows,
                'late' => $late,
                'average_delay_minutes' => round($averageDelay ?? 0, 2),

                'canceled_percentage' => $totalAppointments > 0
                    ? round(($canceled / $totalAppointments) * 100, 2)
                    : 0,

                'no_show_percentage' => $totalAppointments > 0
                    ? round(($noShows / $totalAppointments) * 100, 2)
                    : 0,

                'late_percentage' => $totalAppointments > 0
                    ? round(($late / $totalAppointments) * 100, 2)
                    : 0,

                'specialists_count' => $specialistsCount,
                'clients_count' => $clientsCount,
                'services_count' => $servicesCount,
            ],

            'specialist_load' => $specialistLoad,
            'busy_hours' => $busyHours,
        ]);
    }
}