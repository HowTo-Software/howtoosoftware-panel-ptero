<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HowTooIntegration extends Model
{
    protected $table = 'howtoo_integrations';

    protected $fillable = [
        'provider',
        'enabled',
        'priority',
        'environment_key_enabled',
        'timeout_seconds',
        'secret',
        'model',
    ];

    protected $hidden = ['secret'];

    public function keys(): HasMany
    {
        return $this->hasMany(HowTooIntegrationKey::class, 'provider', 'provider');
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'priority' => 'integer',
            'environment_key_enabled' => 'boolean',
            'timeout_seconds' => 'integer',
            // Encryption uses APP_KEY and occurs before the value reaches the database.
            'secret' => 'encrypted',
        ];
    }
}
