<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChartAdministrationRequest;
use App\Http\Resources\AdministrationResource;
use App\Models\MedicationOrder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdministrationController extends Controller
{
    public function store(
        ChartAdministrationRequest $request,
        MedicationOrder $order
    ): JsonResponse {
        $validated = $request->validated();
        $timezone = config('app.timezone');
        $scheduledFor = Carbon::parse($validated['scheduled_for'])
            ->setTimezone($timezone);

        $isWithinOrderDates = $scheduledFor->toDateString() >= $order->start_date->toDateString()
            && ($order->end_date === null
                || $scheduledFor->toDateString() <= $order->end_date->toDateString());
        $isScheduledDay = in_array(
            $scheduledFor->dayOfWeek,
            array_map('intval', $order->days_of_week),
            true
        );
        $isScheduledTime = $order->times->contains(
            fn ($time): bool => substr($time->time, 0, 5) === $scheduledFor->format('H:i')
        );

        if (! $isWithinOrderDates || ! $isScheduledDay || ! $isScheduledTime) {
            throw ValidationException::withMessages([
                'scheduled_for' => 'The selected date and time are not scheduled for this medication.',
            ]);
        }

        try {
            $administration = DB::transaction(function () use ($order, $scheduledFor, $validated) {
                $lockedOrder = MedicationOrder::query()
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                $scheduledForValue = $scheduledFor->format('Y-m-d H:i:s');
                if ($lockedOrder->administrations()->where('scheduled_for', $scheduledForValue)->exists()) {
                    return null;
                }

                return $lockedOrder->administrations()->create([
                    'scheduled_for' => $scheduledForValue,
                    'status' => $validated['status'],
                    'reason' => $validated['reason'] ?? null,
                    'charted_by' => $validated['charted_by'],
                    'charted_at' => now(),
                ]);
            });
        } catch (QueryException $exception) {
            if (! $this->isDuplicateAdministration($exception)) {
                throw $exception;
            }

            return $this->alreadyChartedResponse();
        }

        if ($administration === null) {
            return $this->alreadyChartedResponse();
        }

        return (new AdministrationResource($administration))
            ->response()
            ->setStatusCode(201);
    }

    private function isDuplicateAdministration(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'order_scheduled_unique')
            || (
                str_contains($message, 'unique')
                && str_contains($message, 'administrations.medication_order_id')
                && str_contains($message, 'administrations.scheduled_for')
            );
    }

    private function alreadyChartedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'This scheduled dose has already been charted.',
        ], 409);
    }
}
