<?php
// "Recuerdame" via cookie con token persistente.
//
// Como funciona:
//
//   1. Tras login, si el usuario marco el checkbox, generamos un token
//      aleatorio de 32 bytes (64 hex). Guardamos el SHA-256 del token
//      en la tabla remember_tokens junto con user_id y expires_at.
//      Enviamos el token sin hashear como cookie HttpOnly + Secure
//      (en HTTPS) + SameSite=Lax con expiracion de 30 dias.
//
//   2. Cuando un visitante llega sin sesion PHP activa pero con la
//      cookie "rt_remember", remember_me_try_restore() busca el hash
//      en BD; si existe y no ha expirado, restaura la sesion.
//
//   3. Al cerrar sesion (logout) o cambiar password, llamamos a
//      remember_me_clear()/remember_me_clear_all_for_user() para
//      revocar el token de BD y borrar la cookie.
//
// Seguridad:
//   - El token solo viaja por TLS (Secure flag) y nunca llega a JS
//     (HttpOnly).
//   - En BD guardamos el hash; aunque la BD se filtre, no se puede
//     reutilizar el token sin colision SHA-256.
//   - El token es one-of-many (un usuario puede tener varias cookies
//     activas en distintos dispositivos); cada sesion en otro
//     dispositivo crea su token y se invalida individualmente.

const REMEMBER_COOKIE = 'rt_remember';
const REMEMBER_DEFAULT_DAYS = 30;

function _remember_ensure_table(): void
{
    global $pdo;
    static $ensured = false;
    if ($ensured) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token_hash (token_hash),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        // FK con CASCADE en su propio try porque MySQL no soporta IF NOT EXISTS.
        try {
            $pdo->exec("ALTER TABLE remember_tokens ADD CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE");
        } catch (Exception $e) { /* ya existe */ }
    } catch (Exception $e) { /* permisos u otro motivo */ }
    $ensured = true;
}

function _remember_cookie_options(int $expires): array
{
    return [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => stripos(SITE_URL, 'https://') === 0,
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function remember_me_set(int $userId, int $days = REMEMBER_DEFAULT_DAYS): void
{
    global $pdo;
    if ($userId <= 0) return;
    _remember_ensure_table();

    $token   = bin2hex(random_bytes(32));
    $hash    = hash('sha256', $token);
    $expires = time() + $days * 86400;

    try {
        $stmt = $pdo->prepare('INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, FROM_UNIXTIME(?))');
        $stmt->execute([$userId, $hash, $expires]);
    } catch (Exception $e) {
        return;
    }

    setcookie(REMEMBER_COOKIE, $token, _remember_cookie_options($expires));
    // Cleanup oportunistico (1% de las llamadas) para que la tabla no
    // crezca indefinidamente con tokens expirados.
    if (random_int(1, 100) === 1) {
        try {
            $pdo->exec("DELETE FROM remember_tokens WHERE expires_at < NOW()");
        } catch (Exception $e) { /* noop */ }
    }
}

function remember_me_clear(?string $token = null): void
{
    global $pdo;
    $token = $token ?? ($_COOKIE[REMEMBER_COOKIE] ?? null);
    if ($token) {
        _remember_ensure_table();
        try {
            $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE token_hash = ?');
            $stmt->execute([hash('sha256', (string)$token)]);
        } catch (Exception $e) { /* noop */ }
    }
    setcookie(REMEMBER_COOKIE, '', _remember_cookie_options(time() - 3600));
    unset($_COOKIE[REMEMBER_COOKIE]);
}

function remember_me_clear_all_for_user(int $userId): void
{
    global $pdo;
    if ($userId <= 0) return;
    _remember_ensure_table();
    try {
        $stmt = $pdo->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
    } catch (Exception $e) { /* noop */ }
}

function remember_me_try_restore(): void
{
    global $pdo;
    if (isset($_SESSION['user_id'])) return;       // ya logueado
    $token = $_COOKIE[REMEMBER_COOKIE] ?? null;
    if (!$token || !is_string($token) || strlen($token) !== 64) return;

    _remember_ensure_table();
    try {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.username, u.email, u.role, u.email_verified
             FROM remember_tokens rt
             JOIN usuarios u ON u.id = rt.user_id
             WHERE rt.token_hash = ? AND rt.expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return;
    }

    if (!$user) {
        // Token invalido/expirado: limpiar cookie del cliente para que
        // no siga enviandose en cada request.
        remember_me_clear($token);
        return;
    }

    // Restaurar sesion exactamente igual que un login normal.
    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email']    = $user['email'];
    $_SESSION['role']     = $user['role'];
}
