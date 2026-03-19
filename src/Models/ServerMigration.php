<?php

namespace Apexsys\ServerMigration\Models;

use Illuminate\Database\Eloquent\Model;

class ServerMigration extends Model
{
    protected $table = 'server_migrations';

    protected $fillable = [
        'old_server_ip',
        'new_server_ip',
        'status',
        'notes',
        'error_log',
        'migrated_at',
    ];

    protected $casts = [
        'migrated_at' => 'datetime',
    ];

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRunning(): bool
    {
        return $this->status === 'running';
    }
}
