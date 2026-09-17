<?php
/**
 * General purpose helper functions shared across the portal.
 */

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): never
{
    $base = rtrim(base_path(), '/');
    header('Location: ' . $base . '/' . ltrim($path, '/'));
    exit;
}

function base_path(): string
{
    // Portal is deployed at the web root; kept as a function so it is easy
    // to point at a sub-directory later without touching every link.
    return '';
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        die('Your session expired or the form token was invalid. Please go back and try again.');
    }
}

function generate_sbt_account_no(PDO $pdo): string
{
    do {
        $candidate = 'SBT-' . date('y') . '-' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE sbt_account_no = ?');
        $stmt->execute([$candidate]);
    } while ((int) $stmt->fetchColumn() > 0);

    return $candidate;
}

function log_activity(?int $userId, string $action, ?string $details = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $action, $details, client_ip()]);
}

function log_security(?int $userId, string $eventType, ?string $details = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO security_logs (user_id, event_type, details, ip_address) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $eventType, $details, client_ip()]);
}

function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function format_datetime(?string $value): string
{
    if (!$value) {
        return '-';
    }
    $ts = strtotime($value);
    return $ts ? date('d M Y, h:i A', $ts) : '-';
}

function format_coins(float $amount): string
{
    return number_format($amount, 2);
}

/**
 * Stream a table's rows as a CSV download. Caller must have already
 * validated access to the data before invoking this.
 *
 * @param array<int, string> $headers
 * @param array<int, array<int, scalar|null>> $rows
 */
function export_csv(string $filename, array $headers, array $rows): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers, ',', '"', '\\');
    foreach ($rows as $row) {
        fputcsv($out, $row, ',', '"', '\\');
    }
    fclose($out);
    exit;
}

function is_lockdown_active(): bool
{
    static $cached = null;
    if ($cached === null) {
        $stmt = db()->query("SELECT setting_value FROM system_settings WHERE setting_key = 'lockdown_mode'");
        $cached = $stmt->fetchColumn() === '1';
    }
    return $cached;
}
