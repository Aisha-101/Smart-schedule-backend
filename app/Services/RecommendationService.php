<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\SpecialistAvailability;
use Carbon\Carbon;

class RecommendationService
{
    /**
     * Main recommendation algorithm
     */
    public function getRecommendedTimes(
        $clientId,
        $specialistId,
        $date
    )
    {
        $slots = $this->availableSlots(
            $specialistId,
            $date
        );

        $results = [];

        foreach($slots as $slot){

            $clientScore = $this->clientReliability(
                $clientId
            );

            $timeScore = $this->timeReliability(
                $slot['start']
            );

            $dayScore = $this->dayReliability(
                $slot['start']
            );

            $loadScore = $this->specialistLoad(
                $specialistId,
                $slot['start']
            );

            // Weighted scoring formula
            $score =
                ($clientScore * 0.4) +
                ($timeScore * 0.3) +
                ($dayScore * 0.2) +
                ($loadScore * 0.1);

            $results[] = [
                'start' => $slot['start'],
                'end'   => $slot['end'],
                'score' => round($score,2)
            ];
        }

        usort(
            $results,
            fn($a,$b)=> $b['score'] <=> $a['score']
        );

        return $results;
    }


    /**
     * Generate only FREE specialist slots
     */
    private function availableSlots(
        $specialistId,
        $date
    )
    {
        $availability=
            SpecialistAvailability::where(
                'specialist_id',
                $specialistId
            )
            ->whereDate(
                'date',
                $date
            )
            ->get();

        $slots=[];

        foreach($availability as $window){

            $start=
                Carbon::parse(
                    $date.' '.$window->start_time
                );

            $end=
                Carbon::parse(
                    $date.' '.$window->end_time
                );


            // 30-minute slots
            while(
                $start->copy()->addMinutes(30)
                    <=
                $end
            ){

                $slotStart=
                    $start->copy();

                $slotEnd=
                    $start->copy()
                        ->addMinutes(30);

                // only add if no conflict
                if(
                    !$this->hasConflict(
                        $specialistId,
                        $slotStart,
                        $slotEnd
                    )
                ){

                    $slots[]=[
                        'start'=>
                            $slotStart
                                ->toDateTimeString(),

                        'end'=>
                            $slotEnd
                                ->toDateTimeString()
                    ];
                }

                $start->addMinutes(30);
            }
        }

        return $slots;
    }


    /**
     * Prevent appointment conflicts
     */
    private function hasConflict(
        $specialistId,
        $start,
        $end
    )
    {
        return Appointment::where(
            'specialist_id',
            $specialistId
        )

        ->where(function($query)
            use($start,$end){

            $query->whereBetween(
                'start_time',
                [$start,$end]
            )

            ->orWhereBetween(
                'end_time',
                [$start,$end]
            )

            ->orWhere(function($q)
                use($start,$end){

                $q->where(
                    'start_time',
                    '<=',
                    $start
                )
                ->where(
                    'end_time',
                    '>=',
                    $end
                );

            });

        })

        ->exists();
    }



    /**
     * Client reliability
     */
    private function clientReliability(
        $clientId
    )
    {
        $appointments=
            Appointment::where(
                'client_id',
                $clientId
            )->get();

        if(
            $appointments->count()==0
        ){
            return 0.70;
        }

        $noShows=
            $appointments
                ->where(
                    'status',
                    'no-show'
                )
                ->count();

        return
            1 -
            ($noShows /
            $appointments->count());
    }



    /**
     * Time preference score
     */
    private function timeReliability(
        $slot
    )
    {
        $hour=
            Carbon::parse(
                $slot
            )->hour;

        if(
            $hour>=9 &&
            $hour<=12
        ){
            return 0.9;
        }

        if(
            $hour<=15
        ){
            return 0.7;
        }

        return 0.5;
    }



    /**
     * Weekday preference
     */
    private function dayReliability(
        $slot
    )
    {
        $day=
            Carbon::parse(
                $slot
            )->dayOfWeek;

        if(
            $day>=1 &&
            $day<=5
        ){
            return 0.9;
        }

        return 0.6;
    }



    /**
     * Specialist load coefficient
     */
    private function specialistLoad(
        $specialistId,
        $slot
    )
    {
        $count=
            Appointment::where(
                'specialist_id',
                $specialistId
            )

            ->whereDate(
                'start_time',
                Carbon::parse(
                    $slot
                )->toDateString()
            )

            ->count();

        return max(
            0,
            1 - ($count/10)
        );
    }
}