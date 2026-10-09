<?php
/**
 * Session-based authentication and role-based access control.
 */

const ADMIN_ROLES = ['mtm', 'tm', 'sysadmin'];
const VIEW_ONLY_ROLES = ['auditor'];

function current_user(): ?array
{
    static $user = null;
    static $loaded = false;

    if ($loaded) {
        return $user;
    }
    $loaded = true;

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT u.*, r.slug AS role_slug, r.name AS role_name, r.access_level
         FROM users u JOIN roles r ON r.id = u.role_id
         WHERE u.id = ? AND u.status = "active"'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;

    if (!$user) {
        // Account was suspended/deleted mid-session.
        session_unset();
        session_destroy();
    }

    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
    return $user;
}

/**
 * @param array<int, string> $slugs role slugs permitted to proceed
 */
function require_role(array $slugs): array
{
    $user = require_login();
    if (!in_array($user['role_slug'], $slugs, true)) {
        http_response_code(403);
        die('You do not have permission to access this page.');
    }
    return $user;
}

function is_admin(?array $user = null): bool
{
    $user ??= current_user();
    return $user && in_array($user['role_slug'], ADMIN_ROLES, true);
}

function is_view_only(?array $user = null): bool
{
    $user ??= current_user();
    return $user && in_array($user['role_slug'], VIEW_ONLY_ROLES, true);
}

function require_write_access(): array
{
    $user = require_login();
    if (is_view_only($user)) {
        http_response_code(403);
        die('Your role has read-only access and cannot perform this action.');
    }
    if (is_lockdown_active() && !is_admin($user)) {
        http_response_code(503);
        die('The portal is currently in emergency lockdown. Only administrators may make changes right now.');
    }
    return $user;
}

function attempt_login(string $email, string $password): ?array
{
    $stmt = db()->prepare(
        'SELECT u.*, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ?'
    );
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        log_security($user['id'] ?? null, 'login_failed', 'Email: ' . $email);
        return null;
    }

    if ($user['status'] !== 'active') {
        log_security($user['id'], 'login_failed', 'Account not active (status: ' . $user['status'] . ')');
        return null;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];

    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);
    log_security($user['id'], 'login_success');
    log_activity($user['id'], 'login', null);

    return $user;
}

function do_logout(): void
{
    $user = current_user();
    if ($user) {
        log_security($user['id'], 'logout');
        log_activity($user['id'], 'logout', null);
    }
    $_SESSION = [];
    session_destroy();
}
