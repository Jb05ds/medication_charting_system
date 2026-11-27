<?php

namespace Tests\Feature;

use App\Models\CareTask;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CreateCareTaskApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_care_task_and_returns_its_computed_status(): void
    {
        $timezone = config('app.timezone');
        $this->travelTo(Carbon::parse('2026-11-27 10:15:00', $timezone));
        $scheduledFor = Carbon::parse('2026-11-27 09:00:00', $timezone);
        $patient = Patient::query()->create([
            'name' => 'Taylor Alpha',
            'room' => '101',
        ]);

        $this->postJson('/api/tasks', [
            'patient_id' => $patient->id,
            'title' => 'Assist with morning routine',
            'scheduled_for' => $scheduledFor->toIso8601String(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.patient_name', 'Taylor Alpha')
            ->assertJsonPath('data.title', 'Assist with morning routine')
            ->assertJsonPath('data.scheduled_for', $scheduledFor->toIso8601String())
            ->assertJsonPath('data.status', 'overdue');

        $this->assertDatabaseHas('care_tasks', [
            'patient_id' => $patient->id,
            'title' => 'Assist with morning routine',
            'scheduled_for' => '2026-11-27 09:00:00',
        ]);
    }

    public function test_it_rejects_missing_or_invalid_task_fields_without_saving(): void
    {
        $this->postJson('/api/tasks', [
            'patient_id' => 999,
            'title' => str_repeat('x', 151),
            'scheduled_for' => 'not-a-date',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['patient_id', 'title', 'scheduled_for']);

        $this->assertDatabaseCount('care_tasks', 0);
    }
}
