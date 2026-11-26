<?php

namespace Tests\Feature;

use App\Models\CareTask;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CompleteCareTaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_completes_a_task_once_and_sets_the_completion_time_on_the_server(): void
    {
        $this->travelTo(Carbon::parse('2026-11-26 10:15:00'));
        $task = $this->createTask();

        $this->postJson("/api/tasks/{$task->id}/complete", [
            'completed_by' => 'Test Nurse',
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.completed_by', 'Test Nurse')
            ->assertJsonPath('data.completed_at', '2026-11-26T10:15:00+00:00');

        $this->postJson("/api/tasks/{$task->id}/complete", [
            'completed_by' => 'Another Nurse',
        ])
            ->assertStatus(409);

        $this->assertDatabaseHas('care_tasks', [
            'id' => $task->id,
            'completed_by' => 'Test Nurse',
            'completed_at' => '2026-11-26 10:15:00',
        ]);
    }

    public function test_it_rejects_missing_names_and_unknown_tasks(): void
    {
        $task = $this->createTask();

        $this->postJson("/api/tasks/{$task->id}/complete", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('completed_by');

        $this->postJson('/api/tasks/999999/complete', [
            'completed_by' => 'Test Nurse',
        ])
            ->assertNotFound();
    }

    private function createTask(): CareTask
    {
        $patient = Patient::query()->create([
            'name' => 'Taylor Alpha',
            'room' => '101',
        ]);

        return CareTask::query()->create([
            'patient_id' => $patient->id,
            'title' => 'Assist with morning routine',
            'scheduled_for' => '2026-11-26 08:00:00',
        ]);
    }
}
