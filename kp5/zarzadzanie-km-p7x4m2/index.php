<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../lib/apartments.php';

const PANEL_PATH = '/zarzadzanie-km-p7x4m2/';

$adminKey = loadAdminKey();
$setupRequired = $adminKey === null;
$accessKey = isset($_GET['k']) && is_string($_GET['k']) ? $_GET['k'] : '';
$sessionOk = !empty($_SESSION['panel_access']);

if ($accessKey !== '' && isValidAdminKey($accessKey)) {
    $_SESSION['panel_access'] = true;
    $sessionOk = true;
    header('Location: ' . PANEL_PATH, true, 303);
    exit;
}

if (!$setupRequired && !$sessionOk) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="pl"><head><meta charset="UTF-8"><title>404</title></head><body><p>Nie znaleziono strony.</p></body></html>';
    exit;
}

$message = '';
$messageError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$setupRequired) {
    $postedKey = $_POST['admin_key'] ?? '';
    if (!isValidAdminKey(is_string($postedKey) ? $postedKey : null)) {
        $message = 'Nieprawidłowy klucz zapisu.';
        $messageError = true;
    } else {
        $input = [];
        foreach (apartmentsDefaults() as $no => $default) {
            $price = filter_input(INPUT_POST, 'price_' . $no, FILTER_VALIDATE_INT);
            $status = $_POST['status_' . $no] ?? $default['status'];
            $input[$no] = [
                'pricePerM2' => $price !== false && $price !== null ? $price : $default['pricePerM2'],
                'status' => is_string($status) ? $status : $default['status'],
            ];
        }

        if (saveApartments($input)) {
            $message = 'Zapisano zmiany. Tabela na stronie głównej zaktualizuje się po odświeżeniu.';
            $messageError = false;
        } else {
            $message = 'Nie udało się zapisać pliku danych. Sprawdź uprawnienia katalogu data/.';
            $messageError = true;
        }
    }
}

$apartments = loadApartments();

function formatArea(float $area): string
{
    return number_format($area, 2, ',', '') . ' m²';
}

function formatPrice(int $value): string
{
    return number_format($value, 0, ',', ' ') . ' zł';
}

function totalPrice(int $pricePerM2, float $area): int
{
    return (int) round($pricePerM2 * $area);
}
?>
<!DOCTYPE html>
<html lang="pl">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Zarządzanie lokalami — Apartamenty Ognisko</title>
    <style>
      :root {
        color-scheme: light;
        --bg: #f6f1ea;
        --card: #fffdf9;
        --text: #2f2a26;
        --muted: #6f655d;
        --accent: #8b4b2d;
        --accent-dark: #6d3921;
        --ok: #28583a;
        --ok-bg: #dfeadd;
        --err: #8a2f2f;
        --err-bg: #f6e4e4;
        --border: #e4d8cb;
      }
      * { box-sizing: border-box; }
      body {
        margin: 0;
        font-family: "Segoe UI", Tahoma, sans-serif;
        background: var(--bg);
        color: var(--text);
        line-height: 1.5;
      }
      .wrap {
        max-width: 1100px;
        margin: 0 auto;
        padding: 24px 16px 48px;
      }
      h1 {
        margin: 0 0 8px;
        font-size: 1.6rem;
      }
      .lead {
        margin: 0 0 24px;
        color: var(--muted);
      }
      .notice {
        padding: 12px 16px;
        border-radius: 10px;
        margin-bottom: 20px;
        border: 1px solid var(--border);
      }
      .notice.ok { background: var(--ok-bg); color: var(--ok); }
      .notice.err { background: var(--err-bg); color: var(--err); }
      .notice.setup { background: #fff8e8; color: #6b4f12; }
      .card {
        background: var(--card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 16px;
        overflow-x: auto;
      }
      table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
      }
      th, td {
        padding: 10px 8px;
        border-bottom: 1px solid var(--border);
        text-align: left;
        vertical-align: middle;
      }
      th {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--muted);
      }
      tr:last-child td { border-bottom: 0; }
      input[type="number"], select {
        width: 100%;
        min-width: 110px;
        padding: 8px 10px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font: inherit;
        background: #fff;
      }
      .total-price {
        font-weight: 700;
        white-space: nowrap;
      }
      .actions {
        margin-top: 20px;
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        align-items: center;
      }
      button, .btn-link {
        appearance: none;
        border: 0;
        border-radius: 999px;
        padding: 12px 22px;
        font: inherit;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
      }
      button {
        background: var(--accent);
        color: #fff;
      }
      button:hover { background: var(--accent-dark); }
      .btn-link {
        background: transparent;
        color: var(--muted);
        border: 1px solid var(--border);
      }
      code {
        background: #efe7dc;
        padding: 2px 6px;
        border-radius: 4px;
      }
      @media (max-width: 720px) {
        th:nth-child(2), td:nth-child(2) { display: none; }
      }
    </style>
  </head>
  <body>
    <div class="wrap">
      <h1>Zarządzanie lokalami</h1>
      <p class="lead">Edycja ceny za m² i statusu lokali wyświetlanych w tabeli na stronie głównej.</p>

      <?php if ($setupRequired): ?>
        <div class="notice setup">
          <strong>Konfiguracja wymagana.</strong>
          Skopiuj plik <code>admin-config.local.example.php</code> jako
          <code>admin-config.local.php</code>, ustaw własny losowy klucz w polu
          <code>admin_key</code>, a następnie wejdź na panel z parametrem
          <code>?k=TWÓJ_KLUCZ</code> (np.
          <code><?= htmlspecialchars(PANEL_PATH, ENT_QUOTES, 'UTF-8') ?>?k=...</code>).
        </div>
      <?php else: ?>
        <?php if ($message !== ''): ?>
          <div class="notice <?= $messageError ? 'err' : 'ok' ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="post" class="card" id="panel-form">
          <input type="hidden" name="admin_key" value="<?= htmlspecialchars($adminKey, ENT_QUOTES, 'UTF-8') ?>" />
          <table>
            <thead>
              <tr>
                <th>Lokal</th>
                <th>Piętro</th>
                <th>Metraż</th>
                <th>Cena za m² (zł)</th>
                <th>Cena łączna</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($apartments as $no => $apt): ?>
                <tr data-area="<?= htmlspecialchars((string) $apt['area'], ENT_QUOTES, 'UTF-8') ?>">
                  <td><strong><?= htmlspecialchars((string) $no, ENT_QUOTES, 'UTF-8') ?></strong></td>
                  <td><?= (int) $apt['floor'] ?></td>
                  <td><?= htmlspecialchars(formatArea((float) $apt['area']), ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <input
                      type="number"
                      name="price_<?= htmlspecialchars((string) $no, ENT_QUOTES, 'UTF-8') ?>"
                      min="1"
                      step="1"
                      value="<?= (int) $apt['pricePerM2'] ?>"
                      data-price-input
                      required
                    />
                  </td>
                  <td class="total-price" data-total-cell>
                    <?= htmlspecialchars(formatPrice(totalPrice((int) $apt['pricePerM2'], (float) $apt['area'])), ENT_QUOTES, 'UTF-8') ?>
                  </td>
                  <td>
                    <select name="status_<?= htmlspecialchars((string) $no, ENT_QUOTES, 'UTF-8') ?>">
                      <option value="available"<?= $apt['status'] === 'available' ? ' selected' : '' ?>>Dostępny</option>
                      <option value="reserved"<?= $apt['status'] === 'reserved' ? ' selected' : '' ?>>Rezerwacja</option>
                      <option value="sold"<?= $apt['status'] === 'sold' ? ' selected' : '' ?>>Sprzedany</option>
                    </select>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          <div class="actions">
            <button type="submit">Zapisz zmiany</button>
            <a class="btn-link" href="/#lista-lokali" target="_blank" rel="noopener">Podgląd tabeli na stronie</a>
          </div>
        </form>
      <?php endif; ?>
    </div>

    <script>
      document.querySelectorAll("[data-price-input]").forEach((input) => {
        input.addEventListener("input", () => {
          const row = input.closest("tr");
          const area = Number(row?.dataset.area || 0);
          const pricePerM2 = Number(input.value);
          const cell = row?.querySelector("[data-total-cell]");
          if (!cell || !area || !Number.isFinite(pricePerM2)) return;
          const total = Math.round(pricePerM2 * area);
          cell.textContent = total.toLocaleString("pl-PL") + " zł";
        });
      });
    </script>
  </body>
</html>
