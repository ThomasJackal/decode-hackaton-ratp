<?php

namespace App\Service\Interface;

interface FinderServiceInterface
{
    public function findDriverByBus(string $busId, string $time): array;

    public function findBusByLineAndStop(string $lineId, string $stopId, string $direction, string $time): array;
}
