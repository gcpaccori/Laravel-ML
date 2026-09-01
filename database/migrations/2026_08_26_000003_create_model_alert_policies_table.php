<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('model_alert_policies')) {
            return;
        }

        Schema::create('model_alert_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('model_code', 120);
            $table->foreignId('piscina_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['draft', 'approved', 'disabled'])->default('draft');
            $table->string('operator', 8);
            $table->decimal('threshold', 14, 5);
            $table->string('unit', 32)->nullable();
            $table->enum('severity', ['advertencia', 'critico', 'emergencia'])->default('advertencia');
            $table->unsignedInteger('version')->default(1);
            $table->text('rationale');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['model_code', 'status']);
            $table->index(['piscina_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_alert_policies');
    }
};
