<?php
declare(strict_types=1);

mb_internal_encoding('UTF-8');

const CONTACT_TO = 'kontakt@apartamentyognisko.pl';
const CONTACT_FROM = 'kontakt@apartamentyognisko.pl';
const CONTACT_FROM_NAME = 'Apartamenty Ognisko';
const CARD_MAIL_DIR = __DIR__ . '/assets/karty/materialy_mail';
const SITE_URL = 'https://apartamentyognisko.pl/';
const PRIVACY_URL = 'https://apartamentyognisko.pl/polityka-prywatnosci/';
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
        'card_file' => "Nie uda\u{0142}o si\u{0119} przygotowa\u{0107} karty lokalu. Napisz do nas na kontakt@apartamentyognisko.pl.",
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

function normalizeApartmentNumber(string $apartment): ?int
{
    if (!preg_match('/\d+/', $apartment, $matches)) {
        return null;
    }

    $number = (int) $matches[0];

    if ($number < 1 || $number > 22) {
        return null;
    }

    return $number;
}

function findCardMailPdf(int $apartmentNo): ?string
{
    $suffix = '-LU' . $apartmentNo . '.pdf';
    $matches = glob(CARD_MAIL_DIR . '/*' . $suffix) ?: [];

    foreach ($matches as $path) {
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

function encodeMailSubject(string $subject): string
{
    return '=?UTF-8?B?' . base64_encode($subject) . '?=';
}

function buildMailHeaders(string $replyTo, ?string $contentType = 'text/plain; charset=UTF-8'): string
{
    $headers = [
        'MIME-Version: 1.0',
        'From: ' . CONTACT_FROM_NAME . ' <' . CONTACT_FROM . '>',
        'Reply-To: ' . $replyTo,
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    if ($contentType !== null) {
        $headers[] = 'Content-Type: ' . $contentType;
    }

    return implode("\r\n", $headers);
}

function sendPlainMail(string $to, string $subject, string $body, string $replyTo): bool
{
    return mail(
        $to,
        encodeMailSubject($subject),
        $body,
        buildMailHeaders($replyTo)
    );
}

function buildCardMailPlainBody(): string
{
    return implode("\n", [
        "Dzie\u{0144} dobry,",
        '',
        "Dzi\u{0119}kujemy za zainteresowanie nasz\u{0105} inwestycj\u{0105} w Krynicy-Zdroju.",
        '',
        "W za\u{0142}\u{0105}czniku przesy\u{0142}amy kart\u{0119} wybranego lokalu z jego najwa\u{017C}niejszymi informacjami.",
        '',
        "Mamy nadziej\u{0119}, \u{017C}e pozwoli ona lepiej pozna\u{0107} wyj\u{0105}tkowy charakter inwestycji. Je\u{015B}li chcieliby Pa\u{0144}stwo uzyska\u{0107} wi\u{0119}cej informacji lub um\u{00F3}wi\u{0107} si\u{0119} na indywidualn\u{0105} prezentacj\u{0119}, pozostajemy do dyspozycji.",
        '',
        "Z przyjemno\u{015B}ci\u{0105} odpowiemy na wszystkie pytania i pomo\u{017C}emy wybra\u{0107} lokal najlepiej dopasowany do Pa\u{0144}stwa oczekiwa\u{0144}. W celu dalszych informacji, prosimy o odpowied\u{017A} na tego e-maila.",
        '',
        'Serdecznie pozdrawiamy,',
        'Apartamenty Ognisko',
    ]);
}

function buildCardMailFooterHtml(): string
{
    $text = '#70463a';
    $bg = '#e4ded1';
    $border = '#c8c0b4';
    $link = 'color:' . $text . ';text-decoration:none;';

    $logo = implode('', [
        '<a href="' . SITE_URL . '" target="_blank" rel="noopener noreferrer" style="text-decoration:none;">',
        '<span style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:30px;font-weight:700;letter-spacing:0.14em;line-height:1.05;color:' . $text . ';">OGNISKO</span>',
        '<span style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:10px;font-weight:400;letter-spacing:0.36em;line-height:1.5;color:' . $text . ';margin-top:5px;">APARTAMENTY</span>',
        '</a>',
    ]);

    return implode("\n", [
        '<table role="presentation" cellpadding="0" cellspacing="0" width="600" style="width:100%;max-width:600px;background-color:' . $bg . ';border-collapse:collapse;">',
        '<tr>',
        '<td style="padding:28px 24px 18px;">',
        '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="border-collapse:collapse;">',
        '<tr>',
        '<td width="32%" style="vertical-align:middle;border-right:1px solid ' . $border . ';padding-right:20px;">',
        $logo,
        '</td>',
        '<td width="36%" style="vertical-align:top;padding:0 18px;font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.85;color:' . $text . ';">',
        '<table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">',
        '<tr><td style="padding:0 10px 0 0;color:' . $text . ';">telefon:</td><td style="font-weight:700;"><a href="tel:+48664663940" style="' . $link . '">+48664663940</a></td></tr>',
        '<tr><td style="padding:0 10px 0 0;color:' . $text . ';">mail:</td><td style="font-weight:700;"><a href="mailto:kontakt@apartamentyognisko.pl" style="' . $link . '">kontakt@apartamentyognisko.pl</a></td></tr>',
        '<tr><td style="padding:0 10px 0 0;color:' . $text . ';">www:</td><td style="font-weight:700;"><a href="' . SITE_URL . '" target="_blank" rel="noopener noreferrer" style="' . $link . '">www.apartamentyognisko.pl</a></td></tr>',
        '</table>',
        '</td>',
        '<td width="32%" style="vertical-align:top;text-align:right;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.65;color:' . $text . ';">',
        '<strong style="font-size:12px;letter-spacing:0.02em;">BOBROWY RESORT &amp; SPA SP. Z O.O.</strong><br />',
        'Ul. Warszawska 6/32, 15-063 Bia\u{0142}ystok<br />',
        'NIP: 966217088 REGON: 523757340',
        '</td>',
        '</tr>',
        '</table>',
        '</td>',
        '</tr>',
        '<tr>',
        '<td style="padding:0 24px 22px;text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:1.5;color:' . $text . ';">',
        'Twoje dane s\u{0105} przetwarzane zgodnie z ',
        '<a href="' . PRIVACY_URL . '" target="_blank" rel="noopener noreferrer" style="color:' . $text . ';font-weight:700;text-decoration:underline;">Polityk\u{0105} Prywatno\u{015B}ci</a>',
        '</td>',
        '</tr>',
        '</table>',
    ]);
}

function buildCardMailHtml(): string
{
    return implode("\n", [
        '<!DOCTYPE html>',
        '<html lang="pl">',
        '<head><meta charset="UTF-8" /></head>',
        '<body style="margin:0;padding:0;font-family:Manrope,Arial,sans-serif;font-size:15px;line-height:1.6;color:#3f2f28;">',
        '<div style="max-width:600px;margin:0 auto;padding:24px 16px;">',
        '<p style="margin:0 0 16px;">Dzie\u{0144} dobry,</p>',
        '<p style="margin:0 0 16px;">Dzi\u{0119}kujemy za zainteresowanie nasz\u{0105} inwestycj\u{0105} w Krynicy-Zdroju.</p>',
        '<p style="margin:0 0 16px;">W za\u{0142}\u{0105}czniku przesy\u{0142}amy kart\u{0119} wybranego lokalu z jego najwa\u{017C}niejszymi informacjami.</p>',
        '<p style="margin:0 0 16px;">Mamy nadziej\u{0119}, \u{017C}e pozwoli ona lepiej pozna\u{0107} wyj\u{0105}tkowy charakter inwestycji. Je\u{015B}li chcieliby Pa\u{0144}stwo uzyska\u{0107} wi\u{0119}cej informacji lub um\u{00F3}wi\u{0107} si\u{0119} na indywidualn\u{0105} prezentacj\u{0119}, pozostajemy do dyspozycji.</p>',
        '<p style="margin:0 0 16px;">Z przyjemno\u{015B}ci\u{0105} odpowiemy na wszystkie pytania i pomo\u{017C}emy wybra\u{0107} lokal najlepiej dopasowany do Pa\u{0144}stwa oczekiwa\u{0144}. W celu dalszych informacji, prosimy o odpowied\u{017A} na tego e-maila.</p>',
        '<p style="margin:0 0 24px;">Serdecznie pozdrawiamy,<br />Apartamenty Ognisko</p>',
        '<div style="margin:32px 0 0;">' . buildCardMailFooterHtml() . '</div>',
        '</div>',
        '</body>',
        '</html>',
    ]);
}

function sendMailWithPdf(
    string $to,
    string $subject,
    string $plainBody,
    string $replyTo,
    string $pdfPath,
    string $attachmentName,
    ?string $htmlBody = null
): bool {
    if (!is_readable($pdfPath)) {
        return false;
    }

    $pdfData = file_get_contents($pdfPath);
    if ($pdfData === false) {
        return false;
    }

    $useHtml = $htmlBody !== null && $htmlBody !== '';
    $mixedBoundary = '=_Mixed_' . bin2hex(random_bytes(8));
    $headers = buildMailHeaders($replyTo, 'multipart/mixed; boundary="' . $mixedBoundary . '"');
    $message = '';

    if ($useHtml) {
        $altBoundary = '=_Alt_' . bin2hex(random_bytes(8));

        $message .= '--' . $mixedBoundary . "\r\n";
        $message .= 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"' . "\r\n\r\n";

        $message .= '--' . $altBoundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $plainBody . "\r\n\r\n";

        $message .= '--' . $altBoundary . "\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $htmlBody . "\r\n\r\n";
        $message .= '--' . $altBoundary . "--\r\n\r\n";
    } else {
        $message .= '--' . $mixedBoundary . "\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $plainBody . "\r\n\r\n";
    }

    $message .= '--' . $mixedBoundary . "\r\n";
    $message .= 'Content-Type: application/pdf; name="' . $attachmentName . "\"\r\n";
    $message .= "Content-Transfer-Encoding: base64\r\n";
    $message .= 'Content-Disposition: attachment; filename="' . $attachmentName . "\"\r\n\r\n";
    $message .= chunk_split(base64_encode($pdfData)) . "\r\n";
    $message .= '--' . $mixedBoundary . '--';

    return mail($to, encodeMailSubject($subject), $message, $headers);
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
    $apartmentNo = normalizeApartmentNumber($apartment);
    if ($apartmentNo === null) {
        respond(false, 'apartment', $redirect);
    }

    $pdfPath = findCardMailPdf($apartmentNo);
    if ($pdfPath === null) {
        respond(false, 'card_file', $redirect);
    }

    $attachmentName = 'Apartamenty-Ognisko-lokal-' . $apartmentNo . '.pdf';
    $userSubject = 'Apartamenty Ognisko - karta Twojego lokalu.';
    $userBody = buildCardMailPlainBody();

    $sentToUser = sendMailWithPdf(
        $email,
        $userSubject,
        $userBody,
        CONTACT_FROM,
        $pdfPath,
        $attachmentName,
        buildCardMailHtml()
    );

    if (!$sentToUser) {
        respond(false, 'send', $redirect);
    }

    $notifySubject = "Wys\u{0142}ano kart\u{0119} lokalu {$apartmentNo} - Apartamenty Ognisko";
    $notifyBody = implode("\n", [
        "Automatycznie wys\u{0142}ano kart\u{0119} lokalu do zainteresowanego.",
        '',
        'Lokal: ' . $apartmentNo,
        'E-mail odbiorcy: ' . $email,
        'Plik: ' . basename($pdfPath),
        '',
        'IP: ' . $ip,
        'Data: ' . date('Y-m-d H:i:s'),
    ]);

    sendPlainMail(CONTACT_TO, $notifySubject, $notifyBody, $email);

    respond(true, "Dzi\u{0119}kujemy. Karta lokalu zosta\u{0142}a wys\u{0142}ana na podany adres e-mail.");
}

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

$sent = sendPlainMail(CONTACT_TO, $subject, $body, $email);

if (!$sent) {
    respond(false, 'send', $redirect);
}

respond(true, "Dzi\u{0119}kujemy za wiadomo\u{015B}\u{0107}. Odezwiemy si\u{0119} wkr\u{00F3}tce.", $redirect);
