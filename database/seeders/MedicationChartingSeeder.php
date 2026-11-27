<?php

namespace Database\Seeders;

use App\Models\CareTask;
use App\Models\MedicationOrder;
use App\Models\Patient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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

            $medicationOrders = [
                [
                    'patient' => $patient1,
                    'drug_name' => 'Demozine',
                    'dose' => '1 tablet',
                    'route' => 'Oral',
                    'days_of_week' => [0, 1, 2, 3, 4, 5, 6],
                    'times' => ['08:00', '20:00'],
                ],
                [
                    'patient' => $patient2,
                    'drug_name' => 'Testafen',
                    'dose' => '2 tablets',
                    'route' => 'Oral',
                    'days_of_week' => [1, 2, 3, 4, 5],
                    'times' => ['09:00'],
                ],
                [
                    'patient' => $patient3,
                    'drug_name' => 'Calmgel',
                    'dose' => '1 application',
                    'route' => 'Topical',
                    'days_of_week' => [1, 3, 5],
                    'times' => ['14:00'],
                ],
            ];

            foreach ($medicationOrders as $medicationOrder) {
                $patient = $medicationOrder['patient'];
                $times = $medicationOrder['times'];
                unset($medicationOrder['patient'], $medicationOrder['times']);

                $order = MedicationOrder::firstOrCreate(
                    [
                        'patient_id' => $patient->id,
                        'drug_name' => $medicationOrder['drug_name'],
                    ],
                    [
                        ...$medicationOrder,
                        'start_date' => today()->subDays(7)->toDateString(),
                        'end_date' => null,
                        'added_by' => 'Demo Nurse',
                    ]
                );

                if ($order->wasRecentlyCreated) {
                    $order->times()->createMany(
                        array_map(fn (string $time): array => ['time' => $time], $times)
                    );
                }
            }

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
                CareTask::firstOrCreate($task);
            }
        });
    }
}
