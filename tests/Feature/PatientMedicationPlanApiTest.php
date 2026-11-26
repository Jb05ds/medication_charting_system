<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientMedicationPlanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_a_patients_orders_with_sorted_times_and_plan_details(): void
    {
        $patient = Patient::query()->create([
            'name' => 'Taylor Alpha',
            'room' => '101',
        ]);
        $otherPatient = Patient::query()->create([
            'name' => 'Taylor Bravo',
            'room' => '102',
        ]);

        $order = $patient->medicationOrders()->create([
            'drug_name' => 'Fictional medicine',
            'dose' => '1 tablet',
            'route' => 'Oral',
            'start_date' => '2026-11-26',
            'end_date' => null,
            'days_of_week' => [1, 3, 5],
            'added_by' => 'Test Nurse',
        ]);
        $order->times()->createMany([
            ['time' => '20:00'],
            ['time' => '08:00'],
        ]);
        $otherPatient->medicationOrders()->create([
            'drug_name' => 'Another medicine',
            'dose' => '1 tablet',
            'route' => 'Oral',
            'start_date' => '2026-11-26',
            'end_date' => null,
            'days_of_week' => [0, 6],
            'added_by' => 'Test Nurse',
        ]);

        $this->getJson("/api/patients/{$patient->id}/orders")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.drug_name', 'Fictional medicine')
            ->assertJsonPath('data.0.dose', '1 tablet')
            ->assertJsonPath('data.0.route', 'Oral')
            ->assertJsonPath('data.0.start_date', '2026-11-26')
            ->assertJsonPath('data.0.end_date', null)
            ->assertJsonPath('data.0.days_of_week', [1, 3, 5])
            ->assertJsonPath('data.0.times', ['08:00', '20:00']);
    }

    public function test_it_returns_not_found_for_an_unknown_patient(): void
    {
        $this->getJson('/api/patients/999999/orders')
            ->assertNotFound();
    }
}
