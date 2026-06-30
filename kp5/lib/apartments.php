<?php
declare(strict_types=1);

const APARTMENTS_FILE = __DIR__ . '/../data/apartments.json';

/** @return array<string, array{floor: int, area: float, pricePerM2: int, status: string}> */
function apartmentsDefaults(): array
{
    return [
        '1' => ['floor' => 0, 'area' => 25.46, 'pricePerM2' => 11300, 'status' => 'available'],
        '2' => ['floor' => 0, 'area' => 55.29, 'pricePerM2' => 9300, 'status' => 'available'],
        '3' => ['floor' => 0, 'area' => 34.70, 'pricePerM2' => 8300, 'status' => 'available'],
        '4' => ['floor' => 0, 'area' => 34.57, 'pricePerM2' => 8300, 'status' => 'available'],
        '5' => ['floor' => 0, 'area' => 18.80, 'pricePerM2' => 11800, 'status' => 'available'],
        '6' => ['floor' => 0, 'area' => 51.25, 'pricePerM2' => 9700, 'status' => 'available'],
        '7' => ['floor' => 1, 'area' => 31.94, 'pricePerM2' => 12300, 'status' => 'available'],
        '8' => ['floor' => 1, 'area' => 55.69, 'pricePerM2' => 11000, 'status' => 'available'],
        '9' => ['floor' => 1, 'area' => 42.90, 'pricePerM2' => 10800, 'status' => 'available'],
        '10' => ['floor' => 1, 'area' => 30.81, 'pricePerM2' => 10800, 'status' => 'available'],
        '11' => ['floor' => 1, 'area' => 37.86, 'pricePerM2' => 12500, 'status' => 'available'],
        '12' => ['floor' => 1, 'area' => 38.63, 'pricePerM2' => 12500, 'status' => 'available'],
        '13' => ['floor' => 2, 'area' => 32.60, 'pricePerM2' => 12500, 'status' => 'available'],
        '14' => ['floor' => 2, 'area' => 60.91, 'pricePerM2' => 9700, 'status' => 'available'],
        '15' => ['floor' => 2, 'area' => 37.52, 'pricePerM2' => 11500, 'status' => 'available'],
        '16' => ['floor' => 2, 'area' => 28.73, 'pricePerM2' => 11500, 'status' => 'available'],
        '17' => ['floor' => 2, 'area' => 43.14, 'pricePerM2' => 11000, 'status' => 'available'],
        '18' => ['floor' => 2, 'area' => 37.09, 'pricePerM2' => 12800, 'status' => 'available'],
        '19' => ['floor' => 2, 'area' => 39.84, 'pricePerM2' => 12800, 'status' => 'available'],
        '20' => ['floor' => 3, 'area' => 29.79, 'pricePerM2' => 13000, 'status' => 'available'],
        '21' => ['floor' => 3, 'area' => 31.13, 'pricePerM2' => 12500, 'status' => 'available'],
        '22' => ['floor' => 3, 'area' => 29.48, 'pricePerM2' => 12500, 'status' => 'available'],
    ];
}

/** @return array<string, array{floor: int, area: float, pricePerM2: int, status: string}> */
function loadApartments(): array
{
    if (!is_file(APARTMENTS_FILE)) {
        return apartmentsDefaults();
    }

    $raw = file_get_contents(APARTMENTS_FILE);
    if ($raw === false) {
        return apartmentsDefaults();
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return apartmentsDefaults();
    }

    return normalizeApartments($data);
}

/** @param array<string, mixed> $data */
function saveApartments(array $data): bool
{
    $normalized = normalizeApartments($data);
    $dir = dirname(APARTMENTS_FILE);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    $tmp = APARTMENTS_FILE . '.tmp';
    if (file_put_contents($tmp, $json . "\n", LOCK_EX) === false) {
        return false;
    }

    return rename($tmp, APARTMENTS_FILE);
}

/**
 * @param array<string, mixed> $data
 * @return array<string, array{floor: int, area: float, pricePerM2: int, status: string}>
 */
function normalizeApartments(array $data): array
{
    $defaults = apartmentsDefaults();
    $result = [];

    foreach ($defaults as $no => $default) {
        $row = $data[$no] ?? [];
        if (!is_array($row)) {
            $row = [];
        }

        $pricePerM2 = (int) ($row['pricePerM2'] ?? $default['pricePerM2']);
        if ($pricePerM2 < 1) {
            $pricePerM2 = $default['pricePerM2'];
        }

        $status = (string) ($row['status'] ?? $default['status']);
        if (!in_array($status, ['available', 'reserved', 'sold'], true)) {
            $status = $default['status'];
        }

        $result[$no] = [
            'floor' => $default['floor'],
            'area' => $default['area'],
            'pricePerM2' => $pricePerM2,
            'status' => $status,
        ];
    }

    return $result;
}

function loadAdminKey(): ?string
{
    $path = __DIR__ . '/../admin-config.local.php';
    if (!is_file($path)) {
        return null;
    }

    $config = require $path;
    if (!is_array($config)) {
        return null;
    }

    $key = $config['admin_key'] ?? null;
    if (!is_string($key) || $key === '' || $key === 'CHANGE_ME_TO_RANDOM_STRING') {
        return null;
    }

    return $key;
}

function isValidAdminKey(?string $provided): bool
{
    $expected = loadAdminKey();
    if ($expected === null || $provided === null || $provided === '') {
        return false;
    }

    return hash_equals($expected, $provided);
}
