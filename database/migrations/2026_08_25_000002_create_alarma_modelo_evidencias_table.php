<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('alarma_modelo_evidencias')) {
            return;
        }

        Schema::create('alarma_modelo_evidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alarma_id')->unique()->constrained('alarmas')->cascadeOnDelete();
            $table->string('source_event_id', 191)->unique();
            $table->string('model_code', 120);
            $table->string('model_version', 80)->nullable();
            $table->string('asset_id', 191)->nullable();
            $table->string('policy_code', 120)->nullable();
            $table->unsignedInteger('horizon_minutes')->nullable();
            $table->timestamp('prediction_for')->nullable();
            $table->decimal('predicted_value', 14, 5)->nullable();
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->index(['model_code', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alarma_modelo_evidencias');
    }
};
