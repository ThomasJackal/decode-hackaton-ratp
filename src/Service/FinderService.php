<?php

namespace App\Service;

use App\Service\Interface\FinderServiceInterface;

final class FinderService implements FinderServiceInterface
{
    public function findDriverByBus(string $busId, string $time): array
    {
        return [
            'driverId' => 6,
        ];
    }

    public function findBusByLineAndStop(string $lineId, string $stopId, string $direction, string $time): array
    {
        return [
            'busId' => 1,
        ];
    }
}