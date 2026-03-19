<?php

namespace Apexsys\ServerMigration\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class DedicatedUser extends Model
{
    protected $table = 'dedicated_users';

    protected $fillable = [
        'name',
        'email',
        'linux_username',
        'public_key',
        'private_key',
        'status',
    ];

    protected $hidden = ['private_key'];

    // Auto-encrypt private key on save
    public function setPrivateKeyAttribute(string $value): void
    {
        $this->attributes['private_key'] = Crypt::encryptString($value);
    }

    // Auto-decrypt private key on read
    public function getPrivateKeyAttribute(?string $value): ?string
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }
}
