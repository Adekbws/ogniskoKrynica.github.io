<?php
declare(strict_types=1);

mb_internal_encoding('UTF-8');

const CONTACT_TO = 'kontakt@apartamentyognisko.pl';
const CONTACT_FROM = 'kontakt@apartamentyognisko.pl';
const CONTACT_FROM_NAME = 'Apartamenty Ognisko';
const MIN_SUBMIT_SECONDS = 3;
const MAX_SUBMIT_SECONDS = 7200;
const RATE_LIMIT_MAX = 5;
const RATE_LIMIT_WINDOW = 3600;

const ALLOWED_REDIRECTS = ['/', '/pod-klucz/'];

function userMessage(string $code, bool $ok = false): string
{
    if ($ok) {
        return $code;
    }

    $messages = [
        'spam' => "Nie uda\u{0142}o si\u{0119} wys\u{0142}a\u{0107} formularza. Spr\u{00F3}buj ponownie lub napisz na kontakt@apartamentyognisko.pl.",
        'timing' => "Formularz wys\u{0142}ano zbyt szybko. Od\u{015B}wie\u{017C} stron\u{0119} i spr\u{00F3}buj ponownie.",
        'rate' => "Zbyt wiele pr\u{00F3}b w kr\u{00F3}tkim czasie. Spr\u{00F3}buj ponownie za chwil\u{0119}.",
        'email' => 'Podaj poprawny adres e-mail.',
        'send' => "Wyst\u{0105}pi\u{0142} b\u{0142}\u{0105}d wysy\u{0142}ki. Napisz do nas na kontakt@apartamentyognisko.pl.",
        'apartment' => "Nie uda\u{0142}o si\u{0119} rozpozna\u{0107} numeru lokalu. Spr\u{00F3}buj ponownie.",
    ];

    return $messages[$code] ?? "Nie uda\u{0142}o si\u{0119} wys\u{0142}a\u{0107} formularza. Spr\u{00F3}buj ponownie.";
}

function respond(bool $ok, string $message, ?string $redirect = null): void
{
    $isAjax = isset($_SERVER['HTTP_ACCEPT'])
        && str_contains((string) $_SERVER['HTTP_ACCEPT'], 'application/json');

    $userMessage = userMessage($message, $ok);

    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($ok ? 200 : 400);
        echo json_encode([
            'ok' => $ok,
            'message' => $userMessage,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $target = $redirect ?? '/';
    if (!in_array($target, ALLOWED_REDIRECTS, true)) {
        $target = '/';
    }
    $separator = str_contains($target, '?') ? '&' : '?';
    $param = $ok ? 'sent=1' : 'error=' . rawurlencode($message);
    header('Location: ' . $target . $separator . $param . '#kontakt', true, 303);
    exit;
}

function clean(string $value, int $maxLength = 500): string
{
    $value = trim(strip_tags($value));
    if (mb_strlen($value) > $maxLength) {
        $value = mb_substr($value, 0, $maxLength);
    }

    return $value;
}

function isSpamContent(string $value): bool
{
    $patterns = [
        '/https?:\/\//i',
        '/\b(viagra|cialis|casino|crypto|forex|porn|xxx)\b/i',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $value)) {
            return true;
        }
    }

    return false;
}

function rateLimitExceeded(string $ip): bool
{
    $dir = __DIR__ . '/data/rate-limits';
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }

    $file = $dir . '/' . hash('sha256', $ip) . '.json';
    $now = time();
    $entries = [];

    if (is_file($file)) {
        $decoded = json_decode((string) file_get_contents($file), true);
        if (is_array($decoded)) {
            $entries = array_values(array_filter(
                $decoded,
                static fn ($timestamp) => is_int($timestamp) && ($now - $timestamp) < RATE_LIMIT_WINDOW
            ));
        }
    }

    if (count($entries) >= RATE_LIMIT_MAX) {
        return true;
    }

    $entries[] = $now;
    file_put_contents($file, json_encode($entries));

    return false;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /', true, 303);
    exit;
}

$redirect = $_POST['redirect'] ?? '/';
if (!in_array($redirect, ALLOWED_REDIRECTS, true)) {
    $redirect = '/';
}

if (!empty($_POST['website'])) {
    respond(false, 'spam', $redirect);
}

$formTs = filter_var($_POST['form_ts'] ?? '', FILTER_VALIDATE_INT);
$now = time();
if (
    $formTs === false
    || ($now - $formTs) < MIN_SUBMIT_SECONDS
    || ($now - $formTs) > MAX_SUBMIT_SECONDS
) {
    respond(false, 'timing', $redirect);
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (rateLimitExceeded($ip)) {
    respond(false, 'rate', $redirect);
}

$formType = clean((string) ($_POST['form_type'] ?? 'contact'), 40);
$email = clean((string) ($_POST['email'] ?? ''), 120);
$name = clean((string) ($_POST['name'] ?? ''), 120);
$phone = clean((string) ($_POST['phone'] ?? ''), 40);
$apartment = clean((string) ($_POST['apartment'] ?? ''), 40);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'email', $redirect);
}

foreach ([$name, $phone, $email, $apartment] as $field) {
    if ($field !== '' && isSpamContent($field)) {
        respond(false, 'spam', $redirect);
    }
}

if ($formType === 'card') {
    if ($apartment === '') {
        respond(false, 'apartment', $redirect);
    }

    $subject = "Pro\u{015B}ba o kart\u{0119} lokalu - Apartamenty Ognisko";
    $body = implode("\n", [
        "Nowa pro\u{015B}ba o przes\u{0142}anie karty lokalu.",
        '',
        'Lokal: ' . $apartment,
        'E-mail: ' . $email,
        '',
        'IP: ' . $ip,
        'Data: ' . date('Y-m-d H:i:s'),
    ]);
} else {
    $subject = 'Zapytanie ze strony - Apartamenty Ognisko';
    $body = implode("\n", [
        'Nowe zapytanie z formularza kontaktowego.',
        '',
        "Imi\u{0119} i nazwisko: " . ($name !== '' ? $name : '(nie podano)'),
        'Telefon: ' . ($phone !== '' ? $phone : '(nie podano)'),
        'E-mail: ' . $email,
        '',
        'IP: ' . $ip,
        'Data: ' . date('Y-m-d H:i:s'),
    ]);
}

$headers = implode("\r\n", [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: ' . CONTACT_FROM_NAME . ' <' . CONTACT_FROM . '>',
    'Reply-To: ' . $email,
    'X-Mailer: PHP/' . PHP_VERSION,
]);

$sent = mail(CONTACT_TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);

if (!$sent) {
    respond(false, 'send', $redirect);
}

if ($formType === 'card') {
    respond(true, "Dzi\u{0119}kujemy. Kart\u{0119} lokalu wy\u{015B}lemy na podany adres e-mail.");
}

respond(true, "Dzi\u{0119}kujemy za wiadomo\u{015B}\u{0107}. Odezwiemy si\u{0119} wkr\u{00F3}tce.", $redirect);
