<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompleteCareTaskRequest;
use App\Http\Resources\CareTaskResource;
use App\Models\CareTask;
use Illuminate\Http\JsonResponse;

class CareTaskController extends Controller
{
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
