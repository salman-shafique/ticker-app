<?php

namespace App\Services;

use App\Exceptions\GeneralException;
use App\Models\Reservation;
use App\Models\ReservationDate;
use App\Services\Backend\ClosingDateService;
use App\Services\Backend\SettingService;
use Arr;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Class ReservationService.
 */
class ReservationService extends BaseService
{
    /**
     * AmenityService constructor.
     *
     */
    public function __construct()
    {
        $this->model = new Reservation();
    }

    public function getOwnerReservations($user, $request)
    {
        return $this->model::whereHas('salon', function ($query) use ($user) {
            $query->where('user_id', $user->id);
        })->when($request->status, function ($query) use ($request) {
            $query->where('status', $request->status);
        })->when($request->status, function ($query) use ($request) {
            $query->where('active', $request->active);
        })->when($request->salon_id, function ($query) use ($request) {
            $query->where('salon_id', $request->salon_id);
        })->with(['salon' => function ($query) {
            $query->select('id', 'name', 'city', 'address', 'state')->with('images');
        }])->with('reservationDates')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    public function getProfessionalReservations($user, $request)
    {
        return $this->model::where('user_id', $user->id)
            ->when($request->status, function ($query) use ($request) {
                $query->where('status', $request->status);
            })->when($request->status, function ($query) use ($request) {
                $query->where('active', $request->active);
            })->when($request->salon_id, function ($query) use ($request) {
                $query->where('salon_id', $request->salon_id);
            })->with(['salon' => function ($query) {
                $query->select('id', 'name', 'city', 'address', 'state')->with('images');
            }])
            ->with('reservationDates')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    public function getReservations($request)
    {
        return $this->model::when($request->status !== null, function ($query) use ($request) {
            $query->where('status', $request->status);
        })->when($request->active, function ($query) use ($request) {
            $query->where('active', $request->active);
        })->when($request->work_station_id && ! empty($request->work_station_id), function ($query) use ($request) {
            $query->where('work_station_id', $request->work_station_id);
        })->when($request->salon_id, function ($query) use ($request) {
            $query->where('salon_id', $request->salon_id);
        })->when($request->salon, function ($query) use ($request) {
            $query->whereHas('salon', function ($query) use ($request) {
                $query->where('name', 'like', "%{$request->salon}%");
            });
        })->when($request->dates, function ($query) use ($request) {

            $query->whereHas('reservationDates', function ($query) use ($request) {
                if ($request->dates == '1') {
                    $query->where('start_date', '<', now()->startOfDay());
                } elseif ($request->dates == '2') {
                    $query->whereBetween('start_date', [now()->startOfDay(), now()->endOfDay()]);
                } elseif ($request->dates == '3') {
                    $query->where('start_date', '>', now()->endOfDay());
                }
            });
        })
            ->with('salon:id,name', 'workstation:id,name')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    /**
     * @param array $data
     *
     * @throws GeneralException
     * @throws Throwable
     */
    public function store(array $data = [])
    {
        DB::beginTransaction();

        try {
            $data['status'] = 0;
            $data['active'] = 0;
            $reservation = $this->model::create(Arr::only($data, $this->model->getFillable()));
            $reservation->reservationDates()->createMany($data['dates']);
        } catch (Exception $e) {
            DB::rollBack();

            return ['status' => false, 'message' => $e->getMessage()];
        }
        DB::commit();

        return ['status' => true, 'reservation' => $reservation];
    }

    public function reservationChecks($data)
    {
        // check for past dates and end date should be greater then start date
        // check for closing dates
        // check for dates within opening hours and holidays
        //check for already booked dates
        $result = ['status' => true, 'message' => 'Reservation checks passed.', 'data' => null];
        $closingDatesService = new ClosingDateService();
        $salonService = new SalonService();

        $closingDates = $closingDatesService->where('salon_id', $data['salon_id'])->get();
        $openingHours = $salonService->getSalonsOpeningHours($data['salon_id']);
        // check if salon has this workstation
        $salon = $salonService->salonHasWorkStation($data['salon_id'], $data['work_station_id']);
        if (empty($salon)) {
            return ['status' => false, 'message' => "Salon doesn't have this workstation."];
        }
        // add a check for weekly days
        $weeksCheck = $this->getWeeks($data['dates'], $data['rate']);
        if ($weeksCheck['status'] == false) {
            return $weeksCheck;
        }
        $dayOrWeeks = $weeksCheck['time'];
        $workstationQuantity = $salon->workstation->first()->pivot->quantity ?? 0;
        // get reservation dates
        $reservationDates = $this->getReservationDates($data['salon_id'], $data['work_station_id']);
        // Total Price of the reservation
        // time can be hours, days and weeks
        $time = 0;
        foreach ($data['dates'] as $date) {
            $startDate = Carbon::parse($date['start_date']);
            $endDate = Carbon::parse($date['end_date']);
            //  Reservation dates validations
            $reservationDatesCheck = $this->reservationDatesCheck($startDate, $endDate);
            if ($reservationDatesCheck['status'] == false) {
                $result = $reservationDatesCheck;

                break;
            }
            //  Salon closing dates validations
            $closingDatesCheck = $this->closingDateCheck($startDate->format('Y-m-d'), $closingDates);
            if ($closingDatesCheck['status'] == false) {
                $result = $closingDatesCheck;

                break;
            }
            //  Salon opening hours validations
            $openingHoursCheck = $this->openingHoursCheck($openingHours, $startDate, $endDate, true, $data['rate']);
            if ($openingHoursCheck['status'] == false) {
                $result = $openingHoursCheck;

                break;
            }
//            dump($openingHoursCheck['data']['time']);
            $time = $time + $openingHoursCheck['data']['time'];
            //  Salon reservation validations
            $slotAvailabilityCheck = $this->slotAvailabilityCheck($reservationDates, $workstationQuantity, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));
            if ($slotAvailabilityCheck == false) {
                $result = ['status' => false, 'message' => 'Slot availability are not available.'];

                break;
            }
        }
        if ($result['status'] == true) {
            $time = $data['rate'] == 'hourly' ? $time : $dayOrWeeks;
            $result['data'] = $this->getReservationAmount($salon, $time, $data['rate']);
        }

        return $result;
    }

    public function reservationAvailabilityChecks($data)
    {
        // check for past dates and end date should be greater then start date
        // check for closing dates
        // check for dates within opening hours and holidays
        //check for already booked dates
        $closingDatesService = new ClosingDateService();
        $salonService = new SalonService();
        $date = Carbon::parse($data['date']);
        //  Check is in future
        if ($date->lessThan(now())) {
            return ['status' => false, 'message' => "Date must be in future."];
        }
        // check if salon has this workstation
        $salon = $salonService->salonHasWorkStation($data['salon_id'], $data['work_station_id']);
        if (empty($salon)) {
            return ['status' => false, 'message' => "Salon doesn't have this workstation."];
        }
        $workstationQuantity = $salon->workstation->first()->pivot->quantity ?? 0;
        // fetch closing dates and closing hours
        $closingDates = $closingDatesService->where('salon_id', $data['salon_id'])->get();
        $openingHours = $salonService->getSalonsOpeningHours($data['salon_id']);

        //  Salon closing dates validations
        $closingDatesCheck = $this->closingDateCheck($data['date'], $closingDates);
        if ($closingDatesCheck['status'] == false) {
            return $closingDatesCheck;
        }
        //  Salon closing dates validations
        $openingHoursCheck = $this->openingHoursCheck($openingHours, $date, null, false);
        if ($openingHoursCheck['status'] == false) {
            return $openingHoursCheck;
        }

        $reservationDates = $this->getReservationDates($data['salon_id'], $data['work_station_id'], $data['date']);
        $availableSlots = $this->getAvailableSlots($reservationDates, $openingHours, $workstationQuantity, $data);

        return ['status' => true, 'slots' => $availableSlots];
    }

    public function closingDateCheck($startDate, $closingDates)
    {
        $result = ['status' => true, 'message' => "Closing date check passed"];
        if ($closingDates->isNotEmpty()) {
            foreach ($closingDates as $closingDate) {
                if ($startDate == $closingDate->date) {
                    $result = ['status' => false, 'message' => "Start date {$startDate} is between salon closing dates."];

                    break;
                }

//                $closingStartTime = $closingDate->start_time ?? '';
//                $closingEndTime = $closingDate->end_time ?? '';
//                $closingStart = Carbon::parse("{$closingDate->date} {$closingStartTime}");
//                $closingEnd = Carbon::parse("{$closingDate->date} {$closingEndTime}");
//                // check reservation falls in closing dates
//                if ($startDate->betweenExcluded($closingStart, $closingEnd)) {
//                    $result = ['status' => false, 'message' => "Start date {$startDate} is between salon closing dates."];
//                }
//                if ($endDate->betweenExcluded($closingStart, $closingEnd)) {
//                    $result = ['status' => false, 'message' => "End date {$endDate} is between salon closing dates."];
//                }
//                // check if closing dates falls in reservation dates
//                if ($closingStart->betweenExcluded($startDate, $endDate)) {
//                    $result = ['status' => false, 'message' => "Closing time {$closingStart} is between reservation dates."];
//                }
//                if ($closingEnd->betweenExcluded($startDate, $endDate)) {
//                    $result = ['status' => false, 'message' => "Closing time {$closingEnd} is between reservation dates."];
//                }
            }
        }

        return $result;
    }

    public function reservationDatesCheck($startDate, $endDate)
    {
        $result = ['status' => true, 'message' => "general date check passed"];
        // check if start and end date are equal or lesser
        if ($endDate->lessThanOrEqualTo($startDate)) {
            $result = ['status' => false, 'message' => "Reservation start date {$startDate} and end date {$endDate} can not be equal or lesser."];
        }
        if ($startDate->lessThan(now()) || $endDate->lessThan(now())) {
            return ['status' => false, 'message' => "{$startDate} - {$endDate} Date must be in future."];
        }
        if ($startDate->format('Y-m-d') !== $endDate->format('Y-m-d')) {
            $result = ['status' => false, 'message' => "Reservation is not on same date {$startDate} - {$endDate} ."];
        }

        return $result;
    }

    public function openingHoursCheck($openingHours, $startDate, $endDate = null, $checkDateTime = false, $rate = null)
    {
        $result = ['status' => true, 'message' => "Salon opening hours check passed", 'data' => ['time' => 0]];
        if ($openingHours->isNotEmpty()) {
            $startDay = $startDate->dayName;
            $openingStartDay = $openingHours->where('day', $startDay)->first();
            // check if salon is closed or not
            if ($openingStartDay->active == 0) {
                return ['status' => false, 'message' => "Salon is closed on {$startDay}"];
            }
            // check if time is within opening hours
            if ($checkDateTime) {
                $salonStartTime = Carbon::parse("{$startDate->format('Y-m-d')} {$openingStartDay->start_time}");
                $salonEndTime = Carbon::parse("{$endDate->format('Y-m-d')} {$openingStartDay->end_time}");
//                $salonStartTime->floatDiffInHours()
                if (! $startDate->betweenIncluded($salonStartTime, $salonEndTime)) {
                    $result = ['status' => false, 'message' => "Start date {$startDate} is not between salon opening hours."];
                }
                if (! $endDate->betweenIncluded($salonStartTime, $salonEndTime)) {
                    $result = ['status' => false, 'message' => "End date {$endDate} is not between salon opening hours."];
                }
            }
            // check for the price
            if (! empty($rate) && in_array($rate, ['hourly', 'daily', 'weekly'])) {
                // calculate hours
                if ($rate == 'hourly') {
                    $result['data']['time'] = $endDate->floatDiffInHours($startDate);
                } else {
                    $salonHours = $salonEndTime->diffInHours($salonStartTime);
                    $reservationHours = $endDate->diffInHours($startDate);
                    // check if if whole day is selected or not.
                    //TODO : add a check to calculate minutes
                    if ($salonHours !== $reservationHours) {
                        $result = ['status' => false, 'message' => "Please select the whole day for daily and weekly reservation. {$startDate} - {$endDate}"];
                    }
                }
            }
        }

        return $result;
    }

    public function getAvailableSlots($reservationDates, $openingHours, $workstationQuantity, $data)
    {
        $slots = [];
        $date = Carbon::parse($data['date']);
        $startDay = $date->dayName;
        $openingStartDay = $openingHours->where('day', $startDay)->first();
        $startDateTime = Carbon::parse("{$data['date']} {$openingStartDay->start_time}");
        $endDateTime = Carbon::parse("{$data['date']} {$openingStartDay->end_time}");
        $slotStart = $startDateTime;
        $slotEnd = $startDateTime;

        if ($data['duration'] == 0) {
            $slotAvailable = $this->slotAvailabilityCheck($reservationDates, $workstationQuantity, $startDateTime->toDateTimeString(), $endDateTime->toDateTimeString());
            if ($slotAvailable) {
                array_push($slots, ['start' => $startDateTime->toDateTimeString(), 'end' => $endDateTime->toDateTimeString()]);
            }
        } else {
            while ($slotStart->lessThanOrEqualTo($endDateTime)) {
                $start = $slotStart->toDateTimeString();
                $end = $slotStart->addMinutes($data['duration']);
                $tmp = ['start' => $start, 'end' => $end->toDateTimeString()];
                $slotAvailable = $this->slotAvailabilityCheck($reservationDates, $workstationQuantity, $tmp['start'], $tmp['end']);
                if ($slotStart->lessThanOrEqualTo($endDateTime) && $slotAvailable) {
                    array_push($slots, $tmp);
                }
            }
        }


        return $slots;
    }

    public function getReservationDates($salonID, $workstationID, $date = null)
    {
        return ReservationDate::when($date !== null, function ($query) use ($date) {
            $query->where('start_date', '>=', "{$date} 00:00:00")
                ->where('start_date', '<=', "{$date} 23:59:00");
        })->when($date === null, function ($query) use ($date) {
            $now = now()->format('Y-m-d');
            $query->where('start_date', '>=', "{$now} 00:00:00");
        })->whereHas('reservation', function ($query) use ($salonID, $workstationID) {
            $query->where('salon_id', $salonID)->where('work_station_id', $workstationID);
        });
    }

    public function slotAvailabilityCheck($reservationDates, $totalStation, $startTime, $endTime)
    {
        $result = ['status' => true, 'message' => "Closing date check passed"];
        $slotStart = Carbon::parse($startTime);
        $slotEnd = Carbon::parse($endTime);
        $counter = 0;
        foreach ($reservationDates as $reservationDate) {
            $availableFlag = 0;
            $reservationStartDate = Carbon::parse($reservationDate->start_date);
            $reservationEndDate = Carbon::parse($reservationDate->end_date);
            if ($slotStart->betweenExcluded($reservationStartDate, $reservationEndDate)) {
                $availableFlag = 1;
                $result = ['status' => false, 'message' => "Start date {$slotStart} is between salon reservation dates."];
            }
            if ($slotEnd->betweenExcluded($reservationStartDate, $reservationEndDate)) {
                $availableFlag = 1;
                $result = ['status' => false, 'message' => "End date {$slotEnd} is between salon reservation dates."];
            }
            //                // check if closing dates falls in reservation dates
            if ($reservationStartDate->betweenExcluded($slotStart, $slotEnd)) {
                $availableFlag = 1;
                $result = ['status' => false, 'message' => "Closing time {$reservationStartDate} is between reservation dates."];
            }
            if ($reservationEndDate->betweenExcluded($slotStart, $slotEnd)) {
                $availableFlag = 1;
                $result = ['status' => false, 'message' => "Closing time {$reservationEndDate} is between reservation dates."];
            }
            if ($availableFlag == 1) {
                $counter++;
            }
        }

        return $counter >= $totalStation ? false : true;
    }

    public function getReservationAmount($salon, $time, $rate)
    {
        $data = [];
        $settingService = new SettingService();
        $setting = $settingService->getSettings(['app_fee']);
        $appFee = 0;
        if ($setting->isNotEmpty()) {
            $appFee = $setting->first()->value;
        }
        $price = 0;
        if ($rate == 'hourly') {
            $price = $salon->hourly_rate * $time;
            $data['rate_price'] = $salon->hourly_rate;
        } elseif ($rate == 'daily') {
            $price = $salon->daily_rate * $time;
            $data['rate_price'] = $salon->daily_rate;
        } elseif ($rate == 'weekly') {
            $price = $salon->weekly_rate * $time;
            $data['rate_price'] = $salon->weekly_rate;
        }
        $data['rate_type'] = $rate;
        $data['fee_percent'] = $appFee;
        $data['fee'] = $appFee > 0 ? ($price / 100) * $appFee : 0;
        $data['time'] = $time;
        $data['price'] = $price;
        $data['discount'] = 0;
        $data['total_amount'] = round($price + $data['fee'], 2);

        return $data;
    }

    public function getWeeks($dates, $rate)
    {
        $result = ['status' => true, 'message' => "", 'time' => 0];
        $totalDates = count($dates);
        if ($rate == 'daily') {
            $result['time'] = $totalDates;
        } elseif ($rate == 'weekly') {
            if ($totalDates % 7 !== 0) {
                return ['status' => false, 'message' => "Please select days for the whole week."];
            }
            $result['time'] = (int)$totalDates / 7;
        }

        return $result;
    }
}
