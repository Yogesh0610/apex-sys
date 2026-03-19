<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('server-migration.tables.migrations', 'server_migrations');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('old_server_ip');
            $table->string('new_server_ip');
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])->default('pending');
            $table->text('notes')->nullable();
            $table->text('error_log')->nullable();
            $table->timestamp('migrated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            config('server-migration.tables.migrations', 'server_migrations')
        );
    }
};
