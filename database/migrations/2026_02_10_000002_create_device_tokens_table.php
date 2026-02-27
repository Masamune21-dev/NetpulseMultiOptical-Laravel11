<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('device_tokens')) {
            return;
        }

        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();
            // Do not enforce FK here because this project may run against an
            // existing `users` table whose `id` type differs (signed vs unsigned,
            // int vs bigint), which breaks FK creation on MySQL.
            $table->unsignedBigInteger('user_id')->index();
            $table->text('token');
            $table->string('platform', 50)->nullable();
            $table->string('device_name', 100)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['token']);
            $table->index(['user_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
