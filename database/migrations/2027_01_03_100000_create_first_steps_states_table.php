<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What one person did with the guided tour of one company: saw the welcome, hid the card or finished it. The progress of
        // each step is not stored: it is computed from the company's own data.
        Schema::create('first_steps_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('welcomed_at')->nullable();
            $table->timestampTz('hidden_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('first_steps_states');
    }
};
