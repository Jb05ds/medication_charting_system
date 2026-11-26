<?php

namespace Tests\Feature;

use App\Models\MedicationOrder;
use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ChartAdministrationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_charts_a_scheduled_dose_and_rejects_duplicate_charting(): void
    {
        $this->travelTo(Carbon::parse('2026-11-26 10:15:00'));
        $order = $this->createOrder();
        $payload = [
            'scheduled_for' => '2026-11-26 08:00:00',
            'status' => 'given',
            'charted_by' => 'Test Nurse',
        ];

        $this->postJson("/api/orders/{$order->id}/administrations", $payload)
            ->assertCreated()
            ->assertJsonPath('data.medication_order_id', $order->id)
            ->assertJsonPath('data.status', 'given')
            ->assertJsonPath('data.charted_by', 'Test Nurse')
            ->assertJsonPath('data.charted_at', '2026-11-26T10:15:00+00:00');

        $this->postJson("/api/orders/{$order->id}/administrations", $payload)
            ->assertStatus(409);

        $this->assertDatabaseCount('administrations', 1);
    }

    public function test_held_or_refused_doses_need_a_reason_and_schedule_must_match_the_order(): void
    {
        $this->travelTo(Carbon::parse('2026-11-26 10:15:00'));
        $order = $this->createOrder();

        $this->postJson("/api/orders/{$order->id}/administrations", [
            'scheduled_for' => '2026-11-26 08:00:00',
            'status' => 'held',
            'charted_by' => 'Test Nurse',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/orders/{$order->id}/administrations", [
            'scheduled_for' => '2026-11-27 08:00:00',
            'status' => 'given',
            'charted_by' => 'Test Nurse',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_for');

        $this->postJson("/api/orders/{$order->id}/administrations", [
            'scheduled_for' => '2026-11-26 08:00:00',
            'status' => 'refused',
            'reason' => 'Patient declined',
            'charted_by' => 'Test Nurse',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'refused')
            ->assertJsonPath('data.reason', 'Patient declined');
    }

    public function test_it_returns_not_found_for_an_unknown_order(): void
    {
        $this->postJson('/api/orders/999999/administrations', [
            'scheduled_for' => '2026-11-26 08:00:00',
            'status' => 'given',
            'charted_by' => 'Test Nurse',
        ])
            ->assertNotFound();
    }

    private function createOrder(): MedicationOrder
    {
        $patient = Patient::query()->create([
            'name' => 'Taylor Alpha',
            'room' => '101',
        ]);
        $order = $patient->medicationOrders()->create([
            'drug_name' => 'Fictional medicine',
            'dose' => '1 tablet',
            'route' => 'Oral',
            'start_date' => '2026-11-26',
            'end_date' => null,
            'days_of_week' => [4],
            'added_by' => 'Test Nurse',
        ]);
        $order->times()->createMany([
            ['time' => '08:00'],
            ['time' => '12:00'],
        ]);

        return $order;
    }
}
