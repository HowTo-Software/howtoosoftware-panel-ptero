<?php

namespace Pterodactyl\Services\HowToo;

use Pterodactyl\Contracts\Repository\NodeRepositoryInterface;

final class ResourceStatus
{
    public static function percentage(int|float $used, int|float $total): ?float
    {
        return $total <= 0 ? null : round(max(0, $used / $total * 100), 1);
    }

    public static function fromPercent(?float $percent): string
    {
        return match (true) {
            $percent === null => 'unknown',
            $percent > NodeRepositoryInterface::THRESHOLD_PERCENTAGE_MEDIUM => 'critical',
            $percent > NodeRepositoryInterface::THRESHOLD_PERCENTAGE_LOW => 'warning',
            default => 'ok',
        };
    }
}
