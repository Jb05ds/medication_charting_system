<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('patient_id')
                ->constrained('patients')
                ->restrictOnDelete();

            $table->string('drug_name', 100);
            $table->string('dose', 50);

            $table->enum('route', [
                'Oral',
                'Topical',
                'Inhaled',
                'Eye drops',
                'Injection',
            ]);

            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->json('days_of_week');
            $table->string('added_by', 100);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_orders');
    }
};
