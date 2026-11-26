<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateMedicationOrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_order_and_its_times_together(): void
    {
        $patient = Patient::query()->create([
            'name' => 'Taylor Alpha',
            'room' => '101',
        ]);

        $this->postJson("/api/patients/{$patient->id}/orders", $this->validPayload())
            ->assertCreated()
            ->assertJsonPath('data.drug_name', 'Fictional medicine')
            ->assertJsonPath('data.dose', '1 tablet')
            ->assertJsonPath('data.route', 'Oral')
            ->assertJsonPath('data.start_date', '2026-11-26')
            ->assertJsonPath('data.days_of_week', [1, 3, 5])
            ->assertJsonPath('data.times', ['08:00', '20:00']);

        $this->assertDatabaseHas('medication_orders', [
            'patient_id' => $patient->id,
            'drug_name' => 'Fictional medicine',
            'added_by' => 'Test Nurse',
        ]);
        $this->assertDatabaseCount('medication_order_times', 2);
    }

    public function test_it_rejects_invalid_dates_days_and_times_without_saving(): void
    {
        $patient = Patient::query()->create([
            'name' => 'Taylor Alpha',
            'room' => '101',
        ]);

        $invalidPayloads = [
            ['payload' => ['end_date' => '2026-11-25'], 'field' => 'end_date'],
            ['payload' => ['days_of_week' => []], 'field' => 'days_of_week'],
            ['payload' => ['times' => ['08:00', '08:00']], 'field' => 'times.1'],
            ['payload' => ['times' => ['06:00', '08:00', '12:00', '18:00', '20:00']], 'field' => 'times'],
        ];

        foreach ($invalidPayloads as $case) {
            $this->postJson(
                "/api/patients/{$patient->id}/orders",
                array_replace($this->validPayload(), $case['payload'])
            )
                ->assertUnprocessable()
                ->assertJsonValidationErrors($case['field']);
        }

        $this->assertDatabaseCount('medication_orders', 0);
        $this->assertDatabaseCount('medication_order_times', 0);
    }

    public function test_it_returns_not_found_for_an_unknown_patient(): void
    {
        $this->postJson('/api/patients/999999/orders', $this->validPayload())
            ->assertNotFound();
    }

    private function validPayload(): array
    {
        return [
            'drug_name' => 'Fictional medicine',
            'dose' => '1 tablet',
            'route' => 'Oral',
            'start_date' => '2026-11-26',
            'end_date' => null,
            'days_of_week' => [1, 3, 5],
            'times' => ['20:00', '08:00'],
            'added_by' => 'Test Nurse',
        ];
    }
}
