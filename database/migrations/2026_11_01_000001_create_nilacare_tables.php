<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel inti NilaCare: kolam, data sensor, event perangkat, alarm, perintah, dan ambang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ponds', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // sama dengan POND_ID di firmware
            $table->string('name');
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('mode')->default('auto');    // auto | manual
            $table->timestamps();
        });

        Schema::create('sensor_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pond_id')->constrained()->cascadeOnDelete();
            $table->decimal('do_mg_l', 5, 2)->nullable();
            $table->decimal('temp_c', 5, 2)->nullable();
            $table->decimal('temp_tank_c', 5, 2)->nullable();
            $table->decimal('ph', 4, 2)->nullable();
            $table->decimal('ntu', 6, 1)->nullable();
            $table->decimal('level_cm', 6, 1)->nullable();
            $table->boolean('float_min')->default(false);
            $table->string('zone_do', 10)->nullable();
            $table->string('zone_temp', 10)->nullable();
            $table->string('zone_ph', 10)->nullable();
            $table->string('zone_ntu', 10)->nullable();
            $table->json('actuators')->nullable();
            $table->string('mode', 10)->nullable();
            $table->string('water_change_state', 20)->nullable();
            $table->string('alarm')->nullable();
            $table->timestamp('received_at')->index();
            $table->index(['pond_id', 'received_at']);
        });

        Schema::create('actuator_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pond_id')->constrained()->cascadeOnDelete();
            $table->string('actuator', 20);
            $table->string('state', 5);                 // on | off
            $table->string('reason')->nullable();
            $table->timestamp('received_at')->index();
        });

        Schema::create('device_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pond_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('detail')->nullable();
            $table->timestamp('received_at')->index();
        });

        Schema::create('alarms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pond_id')->constrained()->cascadeOnDelete();
            $table->string('message');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('notified_at')->nullable();
        });

        Schema::create('command_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pond_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('topic', 10);                // cmd | config
            $table->json('payload');
            $table->string('summary');
            $table->string('status', 10)->default('sent'); // sent | confirmed | failed
            $table->string('error')->nullable();
            $table->timestamp('sent_at', 3);
            $table->timestamp('confirmed_at', 3)->nullable();
        });

        Schema::create('threshold_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pond_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('values');
            $table->timestamp('published_at');
        });
    }

    public function down(): void
    {
        foreach (['threshold_configs', 'command_logs', 'alarms', 'device_events', 'actuator_events', 'sensor_readings', 'ponds'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
