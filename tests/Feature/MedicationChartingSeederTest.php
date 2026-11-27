<?php

namespace Tests\Feature;

use App\Models\CareTask;
use App\Models\MedicationOrder;
use App\Models\Patient;
use Database\Seeders\MedicationChartingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedicationChartingSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_varied_fictional_medication_schedules_idempotently(): void
    {
        $seeder = app(MedicationChartingSeeder::class);
        $seeder->run();

        $this->assertDatabaseCount('patients', 3);
        $this->assertDatabaseCount('medication_orders', 3);
        $this->assertDatabaseCount('medication_order_times', 4);
        $this->assertDatabaseCount('care_tasks', 3);
        $this->assertSame(
            ['Eleanor Brooks', 'Margaret Wilson', 'Robert Anderson'],
            Patient::query()->orderBy('name')->pluck('name')->all()
        );
        $this->assertSame(3, CareTask::query()->whereDate('scheduled_for', today())->count());

        $weekdayOrder = MedicationOrder::query()
            ->with('times')
            ->where('drug_name', 'Testafen')
            ->firstOrFail();

        $this->assertSame([1, 2, 3, 4, 5], $weekdayOrder->days_of_week);
        $this->assertSame(
            ['09:00'],
            $weekdayOrder->times
                ->map(fn ($time): string => substr($time->time, 0, 5))
                ->all()
        );
        $this->assertSame(today()->subDays(7)->toDateString(), $weekdayOrder->start_date->toDateString());

        $dailyOrder = MedicationOrder::query()
            ->with('times')
            ->where('drug_name', 'Demozine')
            ->firstOrFail();
        $this->assertSame([0, 1, 2, 3, 4, 5, 6], $dailyOrder->days_of_week);
        $this->assertSame(
            ['08:00', '20:00'],
            $dailyOrder->times
                ->map(fn ($time): string => substr($time->time, 0, 5))
                ->all()
        );

        $alternateOrder = MedicationOrder::query()
            ->with('times')
            ->where('drug_name', 'Calmgel')
            ->firstOrFail();
        $this->assertSame([1, 3, 5], $alternateOrder->days_of_week);
        $this->assertSame(
            ['14:00'],
            $alternateOrder->times
                ->map(fn ($time): string => substr($time->time, 0, 5))
                ->all()
        );

        $task = CareTask::query()
            ->where('title', 'Assist with morning hygiene')
            ->firstOrFail();
        $task->update([
            'completed_by' => 'Demo Nurse',
            'completed_at' => now(),
        ]);

        $seeder->run();

        $this->assertDatabaseCount('patients', 3);
        $this->assertDatabaseCount('medication_orders', 3);
        $this->assertDatabaseCount('medication_order_times', 4);
        $this->assertDatabaseCount('care_tasks', 3);
        $this->assertSame(3, CareTask::query()->whereDate('scheduled_for', today())->count());
        $this->assertDatabaseHas('care_tasks', [
            'id' => $task->id,
            'completed_by' => 'Demo Nurse',
        ]);
    }
}
