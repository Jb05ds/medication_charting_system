<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListCareTasksRequest;
use App\Http\Requests\ListDailyChartRequest;
use App\Http\Resources\CareTaskResource;
use App\Models\Administration;
use App\Models\CareTask;
use App\Models\MedicationOrder;
use App\Support\ScheduleStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class DailyChartController extends Controller
{
    public function chart(ListDailyChartRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $now = now();
        $date = $now->toDateString();
        $weekday = $now->dayOfWeek;

        $orders = MedicationOrder::query()
            ->with([
                'patient',
                'times',
                'administrations' => fn ($query) => $query->whereDate('scheduled_for', $date),
            ])
            ->whereDate('start_date', '<=', $date)
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date))
            ->when(
                isset($filters['patient_id']),
                fn ($query) => $query->where('patient_id', $filters['patient_id'])
            )
            ->get();

        $items = [];

        foreach ($orders as $order) {
            $daysOfWeek = array_map('intval', $order->days_of_week);
            if (! in_array($weekday, $daysOfWeek, true)) {
                continue;
            }

            $administrations = $order->administrations->keyBy(
                fn (Administration $administration): string => $administration->scheduled_for->format('Y-m-d H:i:s')
            );

            foreach ($order->times as $orderTime) {
                $scheduledFor = Carbon::parse(
                    "{$date} {$orderTime->time}",
                    config('app.timezone')
                );
                $administration = $administrations->get($scheduledFor->format('Y-m-d H:i:s'));
                $status = $administration?->status
                    ?? ScheduleStatus::for($scheduledFor, false, $now);

                if (isset($filters['status']) && $filters['status'] !== $status) {
                    continue;
                }

                $items[] = [
                    'medication_order_id' => $order->id,
                    'patient_id' => $order->patient->id,
                    'patient_name' => $order->patient->name,
                    'room' => $order->patient->room,
                    'drug_name' => $order->drug_name,
                    'dose' => $order->dose,
                    'route' => $order->route,
                    'scheduled_for' => $scheduledFor->toIso8601String(),
                    'status' => $status,
                    'reason' => $administration?->reason,
                    'charted_by' => $administration?->charted_by,
                    'charted_at' => $administration?->charted_at?->toIso8601String(),
                ];
            }
        }

        usort(
            $items,
            fn (array $left, array $right): int => strcmp($left['scheduled_for'], $right['scheduled_for'])
        );

        return response()->json(['data' => $items]);
    }

    public function tasks(ListCareTasksRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $now = now();

        $tasks = CareTask::query()
            ->with('patient')
            ->whereDate('scheduled_for', $now->toDateString())
            ->when(
                isset($filters['patient_id']),
                fn ($query) => $query->where('patient_id', $filters['patient_id'])
            )
            ->orderBy('scheduled_for')
            ->get()
            ->map(function (CareTask $task) use ($filters, $now): ?CareTask {
                $status = ScheduleStatus::for(
                    $task->scheduled_for,
                    $task->completed_at !== null,
                    $now
                );

                if (isset($filters['status']) && $filters['status'] !== $status) {
                    return null;
                }

                $task->setAttribute('computed_status', $status);

                return $task;
            })
            ->filter()
            ->values();

        return CareTaskResource::collection($tasks);
    }
}
