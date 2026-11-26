<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('administrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('medication_order_id')
                ->constrained('medication_orders')
                ->restrictOnDelete();

            $table->dateTime('scheduled_for');

            $table->enum('status', [
                'given',
                'held',
                'refused',
            ]);

            $table->string('reason', 255)->nullable();
            $table->string('charted_by', 100);
            $table->dateTime('charted_at');

            $table->unique(
                ['medication_order_id', 'scheduled_for'],
                'order_scheduled_unique'
            );

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('administrations');
    }
};
