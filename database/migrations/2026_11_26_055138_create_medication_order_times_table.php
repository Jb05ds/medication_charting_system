<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medication_order_times', function (Blueprint $table) {
            $table->id();

            $table->foreignId('medication_order_id')
                ->constrained('medication_orders')
                ->restrictOnDelete();

            $table->time('time');

            $table->unique(
                ['medication_order_id', 'time'],
                'order_time_unique'
            );

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medication_order_times');
    }
};
