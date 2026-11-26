<?php

namespace Tests\Feature;

use App\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PatientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_patients_with_their_allergies(): void
    {
        Patient::query()->create([
            'name' => 'Taylor Alpha',
            'room' => '101',
            'allergies' => 'Penicillin',
        ]);
        Patient::query()->create([
            'name' => 'Taylor Bravo',
            'room' => '102',
            'allergies' => null,
        ]);

        $this->getJson('/api/patients')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Taylor Alpha')
            ->assertJsonPath('data.0.room', '101')
            ->assertJsonPath('data.0.allergies', 'Penicillin')
            ->assertJsonPath('data.1.name', 'Taylor Bravo')
            ->assertJsonPath('data.1.allergies', null);
    }

    public function test_it_returns_an_empty_data_list_when_there_are_no_patients(): void
    {
        $this->getJson('/api/patients')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
