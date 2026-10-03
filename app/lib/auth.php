<?php
declare(strict_types=1);

// Users are stored in data/users/<username>.json. Passwords are hashed with
// password_hash() (bcrypt by default, argon2id where available).

const USERNAME_RE = '/^[a-z0-9_]{3,20}$/';
const LOGIN_MAX_FAILS = 10;
const LOGIN_LOCK_SECONDS = 600;

function current_user(): ?array
{
    static $cache = false;
    if ($cache === false) {
        $cache = isset($_SESSION['username']) ? user_find((string) $_SESSION['username']) : null;
    }
    return $cache;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? null;
        flash('Please log in first.', 'warn');
        redirect('/login');
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['username'] = $user['username'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = (string) ($_POST['_token'] ?? '');
    if ($sent === '' || !hash_equals(csrf_token(), $sent)) {
        abort(403, 'Your form expired. Please go back and try again.');
    }
}

function user_find(string $username): ?array
{
    $username = strtolower(trim($username));
    return preg_match(USERNAME_RE, $username) ? db_read('users', $username) : null;
}

function user_public(array $user): array
{
    unset($user['password_hash'], $user['failed_logins'], $user['locked_until']);
    return $user;
}

function password_hash_safe(string $password): string
{
    $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    return password_hash($password, $algo);
}

/** @return array{0: ?array, 1: array<string,string>} [user, errors] */
function user_create(string $username, string $password, string $passwordConfirm): array
{
    $username = strtolower(trim($username));
    $errors = [];
    if (!preg_match(USERNAME_RE, $username)) {
        $errors['username'] = 'Username must be 3-20 characters: lowercase letters, numbers, underscores.';
    }
    if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
    } elseif (strlen($password) > 200) {
        $errors['password'] = 'Password is too long (200 characters max).';
    } elseif ($password !== $passwordConfirm) {
        $errors['password_confirm'] = 'Passwords do not match.';
    }
    if ($errors) {
        return [null, $errors];
    }

    $lock = db_lock('users');
    try {
        if (db_exists('users', $username)) {
            return [null, ['username' => 'That username is taken.']];
        }
        $user = [
            'username'      => $username,
            'password_hash' => password_hash_safe($password),
            'bio'           => '',
            'created_at'    => db_now(),
            'failed_logins' => 0,
            'locked_until'  => null,
        ];
        db_write('users', $username, $user);
    } finally {
        db_unlock($lock);
    }
    return [$user, []];
}

/** @return array{0: ?array, 1: ?string} [user, error] */
function user_authenticate(string $username, string $password): array
{
    $generic = 'Wrong username or password.';
    if (strlen($password) > 200) {
        return [null, $generic];
    }
    $user = user_find($username);
    if (!$user) {
        // Burn similar time as a real check so usernames are harder to enumerate.
        password_verify($password, '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012345');
        return [null, $generic];
    }
    if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
        return [null, 'Too many failed logins. Try again in a few minutes.'];
    }
    if (!password_verify($password, $user['password_hash'])) {
        $lock = db_lock('users');
        try {
            $fresh = db_read('users', $user['username']) ?? $user;
            $fresh['failed_logins'] = ($fresh['failed_logins'] ?? 0) + 1;
            if ($fresh['failed_logins'] >= LOGIN_MAX_FAILS) {
                $fresh['locked_until'] = gmdate('c', time() + LOGIN_LOCK_SECONDS);
                $fresh['failed_logins'] = 0;
            }
            db_write('users', $fresh['username'], $fresh);
        } finally {
            db_unlock($lock);
        }
        return [null, $generic];
    }
    if (($user['failed_logins'] ?? 0) > 0 || !empty($user['locked_until']) || password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
        $user['failed_logins'] = 0;
        $user['locked_until'] = null;
        if (password_needs_rehash($user['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT)) {
            $user['password_hash'] = password_hash_safe($password);
        }
        db_write('users', $user['username'], $user);
    }
    return [$user, null];
}
