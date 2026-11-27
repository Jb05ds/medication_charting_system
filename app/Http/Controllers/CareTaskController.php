<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteCareTaskRequest;
use App\Http\Requests\StoreCareTaskRequest;
use App\Http\Resources\CareTaskResource;
use App\Models\CareTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use App\Support\ScheduleStatus;

class CareTaskController extends Controller
{
    public function store(StoreCareTaskRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $scheduledFor = Carbon::parse($validated['scheduled_for'])
            ->setTimezone(config('app.timezone'));

        $task = CareTask::query()->create([
            'patient_id' => $validated['patient_id'],
            'title' => $validated['title'],
            'scheduled_for' => $scheduledFor->format('Y-m-d H:i:s'),
        ]);
        $task->load('patient');
        $task->setAttribute(
            'computed_status',
            ScheduleStatus::for($task->scheduled_for, false, now())
        );

        return (new CareTaskResource($task))
            ->response()
            ->setStatusCode(201);
    }

    public function complete(CompleteCareTaskRequest $request, CareTask $task): JsonResponse
    {
        $completedAt = now();
        $updated = CareTask::query()
            ->whereKey($task->id)
            ->whereNull('completed_at')
            ->update([
                'completed_by' => $request->validated('completed_by'),
                'completed_at' => $completedAt,
                'updated_at' => $completedAt,
            ]);

        if ($updated === 0) {
            return response()->json([
                'message' => 'This care task has already been completed.',
            ], 409);
        }

        $task->refresh()->load('patient');
        $task->setAttribute('computed_status', 'done');

        return (new CareTaskResource($task))->response();
    }
}
