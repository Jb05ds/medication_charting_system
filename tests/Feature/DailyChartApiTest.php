<?php

namespace Tests\Feature;

use App\Models\Administration;
use App\Models\CareTask;
use App\Models\MedicationOrder;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyChartApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_only_doses_due_today_with_their_current_status(): void
    {
        $this->travelTo(Carbon::parse('2026-11-26 10:15:00'));

        $patient = $this->createPatient('Taylor Alpha', '101');
        $otherPatient = $this->createPatient('Taylor Bravo', '102');
        $order = $this->createOrder($patient, [4], ['08:00', '09:44', '09:45', '11:00']);
        $this->createOrder($patient, [5], ['10:00']);
        $this->createOrder($otherPatient, [4], ['07:00']);

        Administration::query()->create([
            'medication_order_id' => $order->id,
            'scheduled_for' => '2026-11-26 08:00:00',
            'status' => 'given',
            'reason' => null,
            'charted_by' => 'Test Nurse',
            'charted_at' => '2026-11-26 08:05:00',
        ]);

        $this->getJson('/api/chart?patient_id='.$patient->id)
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.status', 'given')
            ->assertJsonPath('data.1.status', 'overdue')
            ->assertJsonPath('data.2.status', 'due')
            ->assertJsonPath('data.3.status', 'due')
            ->assertJsonPath('data.0.patient_name', 'Taylor Alpha');
    }

    public function test_it_uses_the_facility_timezone_to_determine_the_daily_schedule(): void
    {
        $originalTimezone = date_default_timezone_get();
        config(['app.timezone' => 'Asia/Singapore']);
        date_default_timezone_set('Asia/Singapore');
        $this->travelTo(Carbon::parse('2026-11-26 23:30:00', 'UTC'));

        try {
            $patient = $this->createPatient('Taylor Alpha', '101');
            $this->createOrder($patient, [5], ['08:00']);
            CareTask::query()->create([
                'patient_id' => $patient->id,
                'title' => 'Morning task',
                'scheduled_for' => '2026-11-27 08:00:00',
            ]);

            $this->getJson('/api/chart?patient_id='.$patient->id)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.scheduled_for', '2026-11-27T08:00:00+08:00');

            $this->getJson('/api/tasks?patient_id='.$patient->id)
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.scheduled_for', '2026-11-27T08:00:00+08:00');
        } finally {
            date_default_timezone_set($originalTimezone);
        }
    }

    public function test_it_lists_chart_items_and_tasks_for_a_requested_date(): void
    {
        $this->travelTo(Carbon::parse('2026-11-26 10:15:00'));

        $patient = $this->createPatient('Taylor Alpha', '101');
        $this->createOrder($patient, [5], ['08:00']);
        CareTask::query()->create([
            'patient_id' => $patient->id,
            'title' => 'Tomorrow task',
            'scheduled_for' => '2026-11-27 09:00:00',
        ]);

        $this->getJson('/api/chart?patient_id='.$patient->id.'&date=2026-11-27')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.scheduled_for', '2026-11-27T08:00:00+00:00');

        $this->getJson('/api/tasks?patient_id='.$patient->id.'&date=2026-11-27')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Tomorrow task');

        $this->getJson('/api/chart?date=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');

        $this->getJson('/api/tasks?date=not-a-date')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');
    }

    public function test_it_filters_chart_items_by_status_and_rejects_unknown_statuses(): void
    {
        $this->travelTo(Carbon::parse('2026-11-26 10:15:00'));

        $patient = $this->createPatient('Taylor Alpha', '101');
        $this->createOrder($patient, [4], ['07:00', '11:00']);

        $this->getJson('/api/chart?status=overdue')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'overdue');

        $this->getJson('/api/chart?status=done')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_it_lists_today_tasks_with_due_overdue_and_done_statuses(): void
    {
        $this->travelTo(Carbon::parse('2026-11-26 10:15:00'));

        $patient = $this->createPatient('Taylor Alpha', '101');
        $this->createTask($patient, 'Overdue task', '09:44:00');
        $this->createTask($patient, 'Due task', '09:45:00');
        $this->createTask($patient, 'Completed task', '09:00:00', true);
        CareTask::query()->create([
            'patient_id' => $patient->id,
            'title' => 'Tomorrow task',
            'scheduled_for' => '2026-11-27 09:00:00',
        ]);

        $this->getJson('/api/tasks?patient_id='.$patient->id)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'Completed task')
            ->assertJsonPath('data.0.status', 'done')
            ->assertJsonPath('data.1.title', 'Overdue task')
            ->assertJsonPath('data.1.status', 'overdue')
            ->assertJsonPath('data.2.title', 'Due task')
            ->assertJsonPath('data.2.status', 'due');

        $this->getJson('/api/tasks?status=done')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.completed_by', 'Test Nurse');
    }

    private function createPatient(string $name, string $room): Patient
    {
        return Patient::query()->create([
            'name' => $name,
            'room' => $room,
        ]);
    }

    private function createOrder(Patient $patient, array $days, array $times): MedicationOrder
    {
        $order = $patient->medicationOrders()->create([
            'drug_name' => 'Fictional medicine',
            'dose' => '1 tablet',
            'route' => 'Oral',
            'start_date' => '2026-11-26',
            'end_date' => null,
            'days_of_week' => $days,
            'added_by' => 'Test Nurse',
        ]);
        $order->times()->createMany(
            array_map(fn (string $time): array => ['time' => $time], $times)
        );

        return $order;
    }

    private function createTask(
        Patient $patient,
        string $title,
        string $time,
        bool $completed = false
    ): CareTask {
        return CareTask::query()->create([
            'patient_id' => $patient->id,
            'title' => $title,
            'scheduled_for' => "2026-11-26 {$time}",
            'completed_by' => $completed ? 'Test Nurse' : null,
            'completed_at' => $completed ? '2026-11-26 09:05:00' : null,
        ]);
    }
}
