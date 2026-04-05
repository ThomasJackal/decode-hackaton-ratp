<?php

namespace App\Service;

use App\Entity\BusServing;
use App\Entity\TransitDirection;
use App\Entity\TransitLine;
use App\Entity\TransitStop;
use App\Repository\BusRepository;
use App\Repository\BusServingRepository;
use App\Repository\DriverRepository;
use App\Repository\TransitDirectionRepository;
use App\Repository\TransitLineRepository;
use App\Repository\TransitStopRepository;
use App\Service\Interface\FinderServiceInterface;

final class FinderService implements FinderServiceInterface
{
    public function __construct(
        private readonly DriverRepository $driverRepository,
        private readonly BusRepository $busRepository,
        private readonly BusServingRepository $busServingRepository,
        private readonly TransitLineRepository $transitLineRepository,
        private readonly TransitStopRepository $transitStopRepository,
        private readonly TransitDirectionRepository $transitDirectionRepository,
    ) {
    }

    public function findDriverByBus(string $busId, string $time): array
    {
        $id = $this->driverRepository->findRandomId();
        if (null === $id) {
            return [];
        }

        $busServing = $this->busServingRepository->findRandomByBusId((int) $busId);
        if (null !== $busServing) {
            return [
                'driverId' => $id,
                'transit' => $this->transitFromBusServing($busServing),
            ];
        }

        return [
            'driverId' => $id,
            'transit' => $this->randomTransitSnapshot(),
        ];
    }

    public function findBusByLineAndStop(string $lineId, string $stopId, string $direction, string $time): array
    {
        $lineRaw = trim($lineId);
        $stopRaw = trim($stopId);
        $directionRaw = trim($direction);

        $line = $this->transitLineRepository->findOneByCode($lineRaw)
            ?? $this->transitLineRepository->findOneByCodeInsensitive($lineRaw);
        $stop = $this->transitStopRepository->findOneByCode($stopRaw)
            ?? $this->transitStopRepository->findOneByCodeInsensitive($stopRaw);

        $directionEntity = null;
        if (null !== $line) {
            $directionEntity = $this->transitDirectionRepository->findOneByLineAndCode($line, $directionRaw)
                ?? $this->transitDirectionRepository->findOneByLineAndLabelInsensitive($line, $directionRaw);
        }

        if (null !== $line && null !== $stop && null !== $directionEntity) {
            $serving = $this->busServingRepository->findOneByLineStopAndDirection($line, $stop, $directionEntity);
            if (null !== $serving && null !== $serving->getBus()) {
                return [
                    'busId' => (string) $serving->getBus()->getId(),
                    'transit' => $this->transitFromBusServing($serving),
                ];
            }
        }

        $fallbackBusId = $this->busRepository->findRandomId();
        if (null === $fallbackBusId) {
            return [];
        }

        return [
            'busId' => (string) $fallbackBusId,
            'transit' => $this->transitSnapshotForUnmatchedInputs($line, $stop, $directionEntity, $lineRaw, $stopRaw, $directionRaw),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transitFromBusServing(BusServing $serving): array
    {
        $line = $serving->getLine();
        $stop = $serving->getStop();
        $dir = $serving->getDirection();

        return [
            'line' => $this->lineToArray($line),
            'stop' => $this->stopToArray($stop),
            'direction' => $this->directionToArray($dir),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function randomTransitSnapshot(): array
    {
        $serving = $this->busServingRepository->findRandom();
        if (null !== $serving) {
            return $this->transitFromBusServing($serving);
        }

        $line = $this->transitLineRepository->findRandom();
        $stop = $this->transitStopRepository->findRandom();
        $direction = null !== $line ? $this->transitDirectionRepository->findRandomForLine($line) : null;
        if (null === $direction) {
            $direction = $this->transitDirectionRepository->findRandom();
        }

        return [
            'line' => $this->lineToArray($line),
            'stop' => $this->stopToArray($stop),
            'direction' => $this->directionToArray($direction),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transitSnapshotForUnmatchedInputs(
        ?TransitLine $line,
        ?TransitStop $stop,
        ?TransitDirection $directionEntity,
        string $lineRaw,
        string $stopRaw,
        string $directionRaw,
    ): array {
        $base = $this->randomTransitSnapshot();
        if (null !== $line) {
            $base['line'] = $this->lineToArray($line);
        } else {
            $base['line'] = ['id' => null, 'code' => $lineRaw, 'name' => ''];
        }
        if (null !== $stop) {
            $base['stop'] = $this->stopToArray($stop);
        } else {
            $base['stop'] = ['id' => null, 'code' => $stopRaw, 'name' => ''];
        }
        if (null !== $directionEntity) {
            $base['direction'] = $this->directionToArray($directionEntity);
        } else {
            $base['direction'] = [
                'id' => null,
                'code' => $directionRaw,
                'label' => $directionRaw,
                'line_id' => $line?->getId(),
            ];
        }

        $base['note'] = 'Combinaison ligne / arrêt / direction non référencée ; transit partiellement issu du référentiel démo.';

        return $base;
    }

    /**
     * @return array{id: int|null, code: string, name: string}
     */
    private function lineToArray(?TransitLine $line): array
    {
        if (null === $line) {
            return ['id' => null, 'code' => '', 'name' => ''];
        }

        return [
            'id' => $line->getId(),
            'code' => (string) $line->getCode(),
            'name' => (string) $line->getName(),
        ];
    }

    /**
     * @return array{id: int|null, code: string, name: string}
     */
    private function stopToArray(?TransitStop $stop): array
    {
        if (null === $stop) {
            return ['id' => null, 'code' => '', 'name' => ''];
        }

        return [
            'id' => $stop->getId(),
            'code' => (string) $stop->getCode(),
            'name' => (string) $stop->getName(),
        ];
    }

    /**
     * @return array{id: int|null, code: string, label: string, line_id: int|null}
     */
    private function directionToArray(?TransitDirection $direction): array
    {
        if (null === $direction) {
            return ['id' => null, 'code' => '', 'label' => '', 'line_id' => null];
        }

        return [
            'id' => $direction->getId(),
            'code' => (string) $direction->getCode(),
            'label' => (string) $direction->getLabel(),
            'line_id' => $direction->getLine()?->getId(),
        ];
    }
}
