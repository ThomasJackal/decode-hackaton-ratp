<?php

declare(strict_types=1);

namespace App\Service\Transit;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Autocomplétion via l’API Navitia (données IDFM / réseaux franciliens).
 *
 * @see https://doc.navitia.io/
 *
 * Créez un jeton sur https://navitia.io/inscription/ puis définissez NAVITIA_API_TOKEN.
 */
final class NavitiaTransitAutocomplete implements TransitAutocompleteInterface
{
    private const API_BASE = 'https://api.navitia.io/v1';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $navitiaApiToken,
        private readonly string $coverage,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->navitiaApiToken !== '';
    }

    public function suggestBusLines(string $query, int $limit = 12): array
    {
        $query = trim($query);
        if (strlen($query) < 2 || !$this->isConfigured()) {
            return [];
        }

        $path = sprintf('/coverage/%s/physical_modes/physical_mode:Bus/lines', rawurlencode($this->coverage));
        $data = $this->request($path, [
            'q' => $query,
            'count' => min($limit, 25),
            'depth' => 0,
        ]);

        $lines = $data['lines'] ?? [];
        if ($lines === []) {
            $data = $this->request(sprintf('/coverage/%s/lines', rawurlencode($this->coverage)), [
                'q' => $query,
                'count' => 30,
                'depth' => 0,
            ]);
            $lines = array_values(array_filter(
                $data['lines'] ?? [],
                static fn (array $line): bool => self::lineIsBus($line)
            ));
            $lines = \array_slice($lines, 0, $limit);
        }

        return $this->normalizeLines(\array_slice($lines, 0, $limit));
    }

    public function suggestStopAreas(string $query, ?string $lineNavitiaId = null, int $limit = 12): array
    {
        $query = trim($query);
        if (strlen($query) < 2 || !$this->isConfigured()) {
            return [];
        }

        $limit = min($limit, 25);

        if (null !== $lineNavitiaId && $lineNavitiaId !== '') {
            $path = sprintf(
                '/coverage/%s/lines/%s/stop_areas',
                rawurlencode($this->coverage),
                rawurlencode($lineNavitiaId)
            );
            $data = $this->request($path, [
                'q' => $query,
                'count' => $limit,
                'depth' => 0,
            ]);
            $items = $this->normalizeStopAreasFromKeyedList($data['stop_areas'] ?? []);

            if ($items !== []) {
                return $items;
            }
        }

        $data = $this->request(sprintf('/coverage/%s/places', rawurlencode($this->coverage)), [
            'q' => $query,
            'type[]' => 'stop_area',
            'count' => $limit,
            'depth' => 1,
        ]);

        return $this->normalizeStopAreasFromPlaces($data['places'] ?? []);
    }

    public function suggestDirectionsForLine(string $lineNavitiaId, int $limit = 20): array
    {
        if ($lineNavitiaId === '' || !$this->isConfigured()) {
            return [];
        }

        $path = sprintf(
            '/coverage/%s/lines/%s/routes',
            rawurlencode($this->coverage),
            rawurlencode($lineNavitiaId)
        );
        $data = $this->request($path, [
            'count' => min($limit, 30),
            'depth' => 0,
        ]);

        $routes = $data['routes'] ?? [];
        $out = [];
        foreach ($routes as $route) {
            if (!\is_array($route)) {
                continue;
            }
            $id = isset($route['id']) && \is_string($route['id']) ? $route['id'] : '';
            if ($id === '') {
                continue;
            }
            $label = self::routeLabel($route);
            $out[] = ['id' => $id, 'label' => $label];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function request(string $path, array $query): array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_BASE.$path, [
                'headers' => [
                    'Authorization' => $this->navitiaApiToken,
                    'Accept' => 'application/json',
                ],
                'query' => $query,
                'timeout' => 15,
            ]);

            if ($response->getStatusCode() !== 200) {
                return [];
            }

            /** @var array<string, mixed> $decoded */
            $decoded = $response->toArray();

            return $decoded;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @param list<array<string, mixed>> $lines
     *
     * @return list<array{id: string, label: string, code?: string}>
     */
    private function normalizeLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $id = isset($line['id']) && \is_string($line['id']) ? $line['id'] : '';
            if ($id === '') {
                continue;
            }
            $code = isset($line['code']) && \is_string($line['code']) ? $line['code'] : null;
            $name = isset($line['name']) && \is_string($line['name']) ? $line['name'] : $code ?? $id;
            $commercial = isset($line['commercial_mode']['name']) && \is_string($line['commercial_mode']['name'])
                ? $line['commercial_mode']['name']
                : '';
            $label = $code ? sprintf('Ligne %s — %s', $code, $name) : $name;
            if ($commercial !== '' && !str_contains(mb_strtolower($label), mb_strtolower($commercial))) {
                $label = $commercial.' · '.$label;
            }
            $item = ['id' => $id, 'label' => $label];
            if (null !== $code && $code !== '') {
                $item['code'] = $code;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $stopAreas
     *
     * @return list<array{id: string, label: string}>
     */
    private function normalizeStopAreasFromKeyedList(array $stopAreas): array
    {
        $out = [];
        foreach ($stopAreas as $sa) {
            if (!\is_array($sa)) {
                continue;
            }
            $id = isset($sa['id']) && \is_string($sa['id']) ? $sa['id'] : '';
            if ($id === '') {
                continue;
            }
            $name = isset($sa['name']) && \is_string($sa['name']) ? $sa['name'] : $id;
            $out[] = ['id' => $id, 'label' => $name];
        }

        return $out;
    }

    /**
     * @param list<array<string, mixed>> $places
     *
     * @return list<array{id: string, label: string}>
     */
    private function normalizeStopAreasFromPlaces(array $places): array
    {
        $out = [];
        foreach ($places as $place) {
            if (!\is_array($place)) {
                continue;
            }
            if (($place['embedded_type'] ?? '') !== 'stop_area') {
                continue;
            }
            $sa = $place['stop_area'] ?? null;
            if (!\is_array($sa)) {
                continue;
            }
            $id = isset($sa['id']) && \is_string($sa['id']) ? $sa['id'] : '';
            if ($id === '') {
                continue;
            }
            $name = isset($sa['name']) && \is_string($sa['name']) ? $sa['name'] : ($place['name'] ?? $id);
            if (\is_string($name)) {
                $out[] = ['id' => $id, 'label' => $name];
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $line
     */
    private static function lineIsBus(array $line): bool
    {
        foreach ($line['physical_modes'] ?? [] as $pm) {
            if (!\is_array($pm)) {
                continue;
            }
            $id = $pm['id'] ?? '';
            if ($id === 'physical_mode:Bus') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $route
     */
    private static function routeLabel(array $route): string
    {
        $name = isset($route['name']) && \is_string($route['name']) ? trim($route['name']) : '';
        if ($name !== '') {
            return $name;
        }
        $dir = $route['direction'] ?? null;
        if (\is_array($dir)) {
            $dname = $dir['name'] ?? '';
            if (\is_string($dname) && $dname !== '') {
                return $dname;
            }
        }

        return $route['id'] ?? 'Sens';
    }
}
