<?php

namespace Pterodactyl\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $provider
 * @property string $name
 * @property bool $enabled
 * @property int $priority
 * @property string $secret
 * @property int $failure_count
 * @property string|null $last_failure_reason
 * @property \Illuminate\Support\Carbon|null $last_failed_at
 * @property \Illuminate\Support\Carbon|null $cooldown_until
 */
class HowTooIntegrationKey extends Model
{
    protected $table = 'howtoo_integration_keys';

    protected $fillable = [
        'provider',
        'name',
        'enabled',
        'priority',
        'secret',
        'failure_count',
        'last_failure_reason',
        'last_failed_at',
        'cooldown_until',
    ];

    protected $hidden = ['secret'];

    public function integration(): BelongsTo
    {
        return $this->belongsTo(HowTooIntegration::class, 'provider', 'provider');
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'priority' => 'integer',
            'failure_count' => 'integer',
            'secret' => 'encrypted',
            'last_failed_at' => 'datetime',
            'cooldown_until' => 'datetime',
        ];
    }
}
