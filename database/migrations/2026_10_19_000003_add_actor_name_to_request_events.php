<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_events', fn (Blueprint $table) => $table->string('actor_name')->nullable());
        DB::statement('UPDATE request_events SET actor_name = users.name FROM users WHERE request_events.actor_id = users.id');
        Schema::table('request_events', fn (Blueprint $table) => $table->string('actor_name')->nullable(false)->change());
    }

    public function down(): void
    {
        Schema::table('request_events', fn (Blueprint $table) => $table->dropColumn('actor_name'));
    }
};
