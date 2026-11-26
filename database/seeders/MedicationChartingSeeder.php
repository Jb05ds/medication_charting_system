<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Patient;
use App\Models\CareTask;

class MedicationChartingSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {

            // Create fictional patients
            $patient1 = Patient::firstOrCreate(
                ['name' => 'Margaret Wilson'],
                [
                    'room' => '101',
                    'allergies' => 'Penicillin',
                ]
            );

            $patient2 = Patient::firstOrCreate(
                ['name' => 'Robert Anderson'],
                [
                    'room' => '102',
                    'allergies' => null,
                ]
            );

            $patient3 = Patient::firstOrCreate(
                ['name' => 'Eleanor Brooks'],
                [
                    'room' => '103',
                    'allergies' => 'Latex',
                ]
            );

            // Today's care tasks
            $tasks = [
                [
                    'patient_id' => $patient1->id,
                    'title' => 'Assist with morning hygiene',
                    'scheduled_for' => today()->setTime(8, 0),
                ],
                [
                    'patient_id' => $patient2->id,
                    'title' => 'Check vital signs',
                    'scheduled_for' => today()->setTime(9, 0),
                ],
                [
                    'patient_id' => $patient3->id,
                    'title' => 'Assist with afternoon walk',
                    'scheduled_for' => today()->setTime(14, 0),
                ],
            ];

            foreach ($tasks as $task) {
                CareTask::updateOrCreate(
                    [
                        'patient_id' => $task['patient_id'],
                        'title' => $task['title'],
                        'scheduled_for' => $task['scheduled_for'],
                    ],
                    [
                        'completed_by' => null,
                        'completed_at' => null,
                    ]
                );
            }
        });
    }
}
