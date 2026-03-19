<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = config('server-migration.tables.dedicated_users', 'dedicated_users');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('linux_username')->unique();
            $table->text('public_key')->nullable();
            $table->text('private_key')->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            config('server-migration.tables.dedicated_users', 'dedicated_users')
        );
    }
};
