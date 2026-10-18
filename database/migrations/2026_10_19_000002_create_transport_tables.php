<?php

use App\Enums\RequestState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate')->unique();
            $table->string('name');
            $table->string('type');
            $table->unsignedSmallInteger('passenger_capacity');
            $table->enum('status', ['available', 'unavailable', 'maintenance'])->default('available');
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();
        });
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('contact')->nullable();
            $table->string('license_number')->nullable();
            $table->enum('status', ['available', 'unavailable'])->default('available');
            $table->boolean('is_demo')->default(false);
            $table->timestampsTz();
        });
        Schema::create('trip_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_number')->unique();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->string('office');
            $table->text('purpose');
            $table->string('destination');
            $table->timestampTz('departure_at');
            $table->timestampTz('return_at');
            $table->enum('state', array_column(RequestState::cases(), 'value'))->default('submitted');
            $table->timestampsTz();
            $table->index(['state', 'created_at']);
            $table->index(['requester_id', 'created_at']);
        });
        Schema::create('passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_request_id')->constrained()->restrictOnDelete();
            $table->string('name');
        });
        Schema::create('request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('from_state')->nullable();
            $table->string('to_state');
            $table->text('reason')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['trip_request_id', 'created_at']);
        });
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('driver_id')->constrained()->restrictOnDelete();
            $table->timestampTz('departure_at');
            $table->timestampTz('return_at');
            $table->enum('state', ['active', 'completed', 'cancelled'])->default('active');
            $table->timestampsTz();
            $table->index(['vehicle_id', 'state', 'departure_at', 'return_at'], 'bookings_vehicle_overlap');
            $table->index(['driver_id', 'state', 'departure_at', 'return_at'], 'bookings_driver_overlap');
        });
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->string('disk')->default('local');
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('category');
            $table->timestampsTz();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE vehicles ADD CONSTRAINT vehicles_positive_capacity CHECK (passenger_capacity > 0)');
        DB::statement('ALTER TABLE trip_requests ADD CONSTRAINT trip_requests_valid_dates CHECK (return_at > departure_at)');
        DB::statement('ALTER TABLE bookings ADD CONSTRAINT bookings_valid_dates CHECK (return_at > departure_at)');
        DB::statement('ALTER TABLE attachments ADD CONSTRAINT attachments_positive_size CHECK (size > 0)');
    }

    public function down(): void
    {
        foreach (['notifications', 'attachments', 'bookings', 'request_events', 'passengers', 'trip_requests', 'drivers', 'vehicles'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
