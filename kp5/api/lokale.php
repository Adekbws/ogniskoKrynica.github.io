<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/apartments.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, max-age=0');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode(loadApartments(), JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $providedKey = $_SERVER['HTTP_X_ADMIN_KEY'] ?? ($_POST['admin_key'] ?? '');
    if (!isValidAdminKey(is_string($providedKey) ? $providedKey : null)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Brak uprawnie?.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    $payload = json_decode($raw !== false ? $raw : '', true);
    if (!is_array($payload)) {
        $payload = $_POST['apartments'] ?? null;
        if (is_string($payload)) {
            $payload = json_decode($payload, true);
        }
    }

    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Nieprawid?owe dane.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!saveApartments($payload)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Nie uda?o si? zapisa? zmian.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => true, 'message' => 'Zapisano zmiany.'], JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'message' => 'Metoda niedozwolona.'], JSON_UNESCAPED_UNICODE);
