<?php
declare(strict_types=1);

mb_internal_encoding('UTF-8');

const CONTACT_TO = 'kontakt@apartamentyognisko.pl';
const CONTACT_FROM = 'kontakt@apartamentyognisko.pl';
const CONTACT_FROM_NAME = 'Apartamenty Ognisko';
const CARD_MAIL_DIR = __DIR__ . '/assets/karty/materialy_mail';
const SITE_URL = 'https://apartamentyognisko.pl/';
const PRIVACY_URL = 'https://apartamentyognisko.pl/polityka-prywatnosci/';
const MAIL_LOGO_URL = SITE_URL . 'assets/logo_stopka.png';
const MAIL_FOOTER_WIDTH = 600;
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
        'spam' => 'Nie udało się wysłać formularza. Spróbuj ponownie lub napisz na kontakt@apartamentyognisko.pl.',
        'timing' => 'Formularz wysłano zbyt szybko. Odśwież stronę i spróbuj ponownie.',
        'rate' => 'Zbyt wiele prób w krótkim czasie. Spróbuj ponownie za chwilę.',
        'email' => 'Podaj poprawny adres e-mail.',
        'send' => 'Wystąpił błąd wysyłki. Napisz do nas na kontakt@apartamentyognisko.pl.',
        'apartment' => 'Nie udało się rozpoznać numeru lokalu. Spróbuj ponownie.',
        'card_file' => 'Nie udało się przygotować karty lokalu. Napisz do nas na kontakt@apartamentyognisko.pl.',
    ];

    return $messages[$code] ?? 'Nie udało się wysłać formularza. Spróbuj ponownie.';
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
    $serverName = (string) ($_SERVER['SERVER_NAME'] ?? 'apartamentyognisko.pl');
    $messageIdHost = preg_replace('/[^a-z0-9.\-]/i', '', $serverName) ?: 'apartamentyognisko.pl';
    $headers = [
        'MIME-Version: 1.0',
        'From: ' . encodeMailSubject(CONTACT_FROM_NAME) . ' <' . CONTACT_FROM . '>',
        'Reply-To: ' . $replyTo,
        'Date: ' . date(DATE_RFC2822),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $messageIdHost . '>',
    ];

    if ($contentType !== null) {
        $headers[] = 'Content-Type: ' . $contentType;
    }

    return implode("\r\n", $headers);
}

function smtpEnv(string $name): ?string
{
    $value = getenv($name);
    if (is_string($value) && $value !== '') {
        return $value;
    }

    if (isset($_SERVER[$name]) && is_string($_SERVER[$name]) && $_SERVER[$name] !== '') {
        return $_SERVER[$name];
    }

    if (function_exists('apache_getenv')) {
        $apacheValue = apache_getenv($name);
        if (is_string($apacheValue) && $apacheValue !== '') {
            return $apacheValue;
        }
    }

    return null;
}

function smtpLocalConfig(): ?array
{
    static $config = null;

    if ($config !== null) {
        return $config;
    }

    $path = __DIR__ . '/mail-config.local.php';
    if (!is_readable($path)) {
        return null;
    }

    $loaded = require $path;
    $config = is_array($loaded) ? $loaded : null;

    return $config;
}

function smtpConfig(): array
{
    $local = smtpLocalConfig();

    $host = smtpEnv('SMTP_HOST') ?? ($local['host'] ?? null);
    $port = smtpEnv('SMTP_PORT') ?? (isset($local['port']) ? (string) $local['port'] : null);
    $user = smtpEnv('SMTP_USER') ?? ($local['user'] ?? null);
    $pass = smtpEnv('SMTP_PASS') ?? ($local['pass'] ?? null);
    $secure = smtpEnv('SMTP_SECURE') ?? ($local['secure'] ?? null);

    return [
        'host' => is_string($host) && $host !== '' ? $host : 'mail86.mydevil.net',
        'port' => is_string($port) && ctype_digit($port) ? (int) $port : 587,
        'user' => is_string($user) && $user !== '' ? $user : CONTACT_FROM,
        'pass' => is_string($pass) ? $pass : '',
        'secure' => is_string($secure) && $secure !== '' ? strtolower($secure) : 'starttls',
    ];
}

function smtpReadResponse($socket): ?string
{
    $response = '';

    while (!feof($socket)) {
        $line = fgets($socket, 515);
        if ($line === false) {
            break;
        }
        $response .= $line;

        if (strlen($line) >= 4 && $line[3] === ' ') {
            break;
        }
    }

    return $response !== '' ? $response : null;
}

function smtpExpect($socket, array $okCodes): bool
{
    $response = smtpReadResponse($socket);
    if ($response === null || strlen($response) < 3) {
        return false;
    }

    $code = (int) substr($response, 0, 3);
    return in_array($code, $okCodes, true);
}

function smtpCommand($socket, string $command, array $okCodes): bool
{
    if (fwrite($socket, $command . "\r\n") === false) {
        return false;
    }

    return smtpExpect($socket, $okCodes);
}

function smtpDotStuff(string $message): string
{
    $normalized = preg_replace("/\r\n|\r|\n/", "\r\n", $message) ?? $message;
    return preg_replace('/(?m)^\./', '..', $normalized) ?? $normalized;
}

function sendViaSmtp(string $to, string $subject, string $body, string $headers): bool
{
    $cfg = smtpConfig();
    if ($cfg['pass'] === '') {
        return false;
    }

    $socket = @stream_socket_client(
        'tcp://' . $cfg['host'] . ':' . (string) $cfg['port'],
        $errno,
        $errstr,
        15
    );

    if ($socket === false) {
        return false;
    }

    stream_set_timeout($socket, 20);

    $helo = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
    if (!smtpExpect($socket, [220])
        || !smtpCommand($socket, 'EHLO ' . $helo, [250])) {
        fclose($socket);
        return false;
    }

    if ($cfg['secure'] === 'starttls') {
        if (!smtpCommand($socket, 'STARTTLS', [220])
            || !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
            || !smtpCommand($socket, 'EHLO ' . $helo, [250])) {
            fclose($socket);
            return false;
        }
    }

    if (!smtpCommand($socket, 'AUTH LOGIN', [334])
        || !smtpCommand($socket, base64_encode($cfg['user']), [334])
        || !smtpCommand($socket, base64_encode($cfg['pass']), [235])
        || !smtpCommand($socket, 'MAIL FROM:<' . CONTACT_FROM . '>', [250])
        || !smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251])
        || !smtpCommand($socket, 'DATA', [354])) {
        fclose($socket);
        return false;
    }

    $data = implode("\r\n", [
        'To: ' . $to,
        'Subject: ' . encodeMailSubject($subject),
        $headers,
        '',
        smtpDotStuff($body),
    ]);

    if (fwrite($socket, $data . "\r\n.\r\n") === false || !smtpExpect($socket, [250])) {
        fclose($socket);
        return false;
    }

    smtpCommand($socket, 'QUIT', [221]);
    fclose($socket);
    return true;
}

function sendPlainMail(string $to, string $subject, string $body, string $replyTo): bool
{
    return sendViaSmtp($to, $subject, $body, buildMailHeaders($replyTo));
}

function buildCardMailPlainBody(): string
{
    return implode("\n", [
        'Dzień dobry,',
        '',
        'Dziękujemy za zainteresowanie naszą inwestycją w Krynicy-Zdroju.',
        '',
        'W załączniku przesyłamy kartę wybranego lokalu z jego najważniejszymi informacjami.',
        '',
        'Mamy nadzieję, że pozwoli ona lepiej poznać wyjątkowy charakter inwestycji. Jeśli chcieliby Państwo uzyskać więcej informacji lub umówić się na indywidualną prezentację, pozostajemy do dyspozycji.',
        '',
        'Z przyjemnością odpowiemy na wszystkie pytania i pomożemy wybrać lokal najlepiej dopasowany do Państwa oczekiwań. W celu dalszych informacji, prosimy o odpowiedź na tego e-maila.',
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
    $link = 'color:' . $text . ';text-decoration:none;font-weight:700;';
    $wrap = 'word-break:break-word;overflow-wrap:break-word;';
    $logoSrc = htmlspecialchars(MAIL_LOGO_URL, ENT_QUOTES, 'UTF-8');
    $footerWidth = MAIL_FOOTER_WIDTH;
    $padH = 20;
    $innerWidth = $footerWidth - ($padH * 2);
    $colLogo = 142;
    $colLogoCss = 100;
    $colRight = 418;
    $logoImgWidth = 118;

    $logo = implode('', [
        '<a href="' . SITE_URL . '" target="_blank" rel="noopener noreferrer" style="text-decoration:none;display:block;">',
        '<img src="' . $logoSrc . '" alt="Apartamenty Ognisko" width="' . $logoImgWidth . '" style="display:block;width:' . $logoImgWidth . 'px;max-width:100%;height:auto;border:0;" />',
        '</a>',
    ]);

    $contactRow = static function (string $label, string $valueHtml): string {
        return '<tr>'
            . '<td width="52" style="width:52px;padding:1px 8px 1px 0;text-align:right;font-weight:400;vertical-align:top;white-space:nowrap;">' . $label . '</td>'
            . '<td style="font-weight:700;vertical-align:top;word-break:break-word;overflow-wrap:break-word;">' . $valueHtml . '</td>'
            . '</tr>';
    };

    return implode("\n", [
        '<table role="presentation" cellpadding="0" cellspacing="0" width="' . $footerWidth . '" align="center" style="width:' . $footerWidth . 'px;max-width:' . $footerWidth . 'px;background-color:' . $bg . ';border-collapse:collapse;table-layout:fixed;margin:0 auto;">',
        '<tr>',
        '<td style="padding:22px ' . $padH . 'px 16px;">',
        '<table role="presentation" cellpadding="0" cellspacing="0" width="' . $innerWidth . '" style="width:' . $innerWidth . 'px;border-collapse:collapse;table-layout:fixed;">',
        '<tr>',
        '<td width="' . $colLogo . '" style="width:' . $colLogoCss . 'px;vertical-align:middle;border-right:1px solid ' . $border . ';padding-right:14px;">',
        $logo,
        '</td>',
        '<td width="' . $colRight . '" style="width:' . $colRight . 'px;vertical-align:middle;padding-left:16px;font-family:Arial,Helvetica,sans-serif;color:' . $text . ';">',
        '<table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="width:100%;border-collapse:collapse;font-size:11px;line-height:1.75;">',
        $contactRow('telefon:', '<a href="tel:+48664663940" style="' . $link . '">+48664663940</a>'),
        $contactRow('mail:', '<a href="mailto:kontakt@apartamentyognisko.pl" style="' . $link . '">kontakt@apartamentyognisko.pl</a>'),
        $contactRow('www:', '<a href="' . SITE_URL . '" target="_blank" rel="noopener noreferrer" style="' . $link . '">www.apartamentyognisko.pl</a>'),
        '</table>',
        '<div style="margin-top:10px;text-align:right;font-size:10px;line-height:1.55;font-weight:400;' . $wrap . '">',
        '<strong style="display:block;font-size:11px;font-weight:700;line-height:1.4;margin-bottom:2px;">BOBROWY RESORT &amp; SPA SP. Z O.O.</strong>',
        'Ul. Warszawska 6/32, 15-063 Białystok<br />',
        'NIP: 966217088 REGON: 523757340',
        '</div>',
        '</td>',
        '</tr>',
        '</table>',
        '</td>',
        '</tr>',
        '<tr>',
        '<td style="padding:0 ' . $padH . 'px 22px;text-align:center;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.5;color:' . $text . ';">',
        'Twoje dane są przetwarzane zgodnie z ',
        '<a href="' . PRIVACY_URL . '" target="_blank" rel="noopener noreferrer" style="color:' . $text . ';font-weight:700;text-decoration:underline;">Polityką Prywatności</a>',
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
        '<p style="margin:0 0 16px;">Dzień dobry,</p>',
        '<p style="margin:0 0 16px;">Dziękujemy za zainteresowanie naszą inwestycją w Krynicy-Zdroju.</p>',
        '<p style="margin:0 0 16px;">W załączniku przesyłamy kartę wybranego lokalu z jego najważniejszymi informacjami.</p>',
        '<p style="margin:0 0 16px;">Mamy nadzieję, że pozwoli ona lepiej poznać wyjątkowy charakter inwestycji. Jeśli chcieliby Państwo uzyskać więcej informacji lub umówić się na indywidualną prezentację, pozostajemy do dyspozycji.</p>',
        '<p style="margin:0 0 16px;">Z przyjemnością odpowiemy na wszystkie pytania i pomożemy wybrać lokal najlepiej dopasowany do Państwa oczekiwań. W celu dalszych informacji, prosimy o odpowiedź na tego e-maila.</p>',
        '<p style="margin:0 0 24px;">Serdecznie pozdrawiamy,<br />Apartamenty Ognisko</p>',
        '</div>',
        buildCardMailFooterHtml(),
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

    return sendViaSmtp($to, $subject, $message, $headers);
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

    $notifySubject = 'Wysłano kartę lokalu ' . $apartmentNo . ' - Apartamenty Ognisko';
    $notifyBody = implode("\n", [
        'Automatycznie wysłano kartę lokalu do zainteresowanego.',
        '',
        'Lokal: ' . $apartmentNo,
        'E-mail odbiorcy: ' . $email,
        'Plik: ' . basename($pdfPath),
        '',
        'IP: ' . $ip,
        'Data: ' . date('Y-m-d H:i:s'),
    ]);

    sendPlainMail(CONTACT_TO, $notifySubject, $notifyBody, $email);

    respond(true, 'Dziękujemy. Karta lokalu została wysłana na podany adres e-mail.');
}

$subject = 'Zapytanie ze strony - Apartamenty Ognisko';
$body = implode("\n", [
    'Nowe zapytanie z formularza kontaktowego.',
    '',
    'Imię i nazwisko: ' . ($name !== '' ? $name : '(nie podano)'),
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

respond(true, 'Dziękujemy za wiadomość. Odezwiemy się wkrótce.', $redirect);