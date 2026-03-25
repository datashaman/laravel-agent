<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('laravel-agent.database.table', 'agent_tasks'), function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('status')->default('pending')->index();
            $table->text('description')->default('');
            $table->longText('output')->default('');
            $table->integer('exit_code')->nullable();
            $table->integer('process_id')->nullable();
            $table->json('dependencies')->default('[]');
            $table->double('created_at');
            $table->double('updated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('laravel-agent.database.table', 'agent_tasks'));
    }
};
