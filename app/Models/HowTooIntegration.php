<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;

class HowTooIntegration extends Model
{
    protected $table = 'howtoo_integrations';

    protected $fillable = ['provider', 'enabled', 'secret', 'model'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            // Encryption uses APP_KEY and occurs before the value reaches the database.
            'secret' => 'encrypted',
        ];
    }
}
