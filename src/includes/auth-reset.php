<?php
// Reset de password por email con token de un solo uso.
//
// Flujo:
//   1. forgot-password.php pide email -> password_reset_create($email)
//      genera un token de 32 bytes (64 hex), guarda SHA-256 en BD con
//      expires_at = NOW() + 1h, marca tokens previos del mismo usuario
//      como ya usados, y envia email con el link al reset-password.php.
//
//   2. reset-password.php?token=X -> password_reset_lookup($token)
//      devuelve los datos del usuario si el token es valido (existe,
//      no usado, no expirado). El form se renderiza solo si ok.
//
//   3. reset-password.php POST -> password_reset_consume($token, $new):
//      hashea la nueva password, actualiza usuario, marca el token
//      como usado y revoca todas las cookies "Recuerdame" del usuario
//      por compromiso de seguridad.
//
// Anti-enumeracion: el caller (forgot-password.php) NUNCA debe revelar
// si el email existia. El mensaje al usuario es siempre el mismo.
//
// Anti-spam: el rate limit de "forgot" (3/60min) que ya existe en
// includes/config.php sigue aplicandose ANTES de password_reset_create.

const RESET_TOKEN_TTL_SECONDS = 3600;
const RESET_COOKIE_PATH       = '/';

function _password_reset_ensure_table(): void
{
    global $pdo;
    static $ensured = false;
    if ($ensured) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token_hash CHAR(64) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_pr_token_hash (token_hash),
            INDEX idx_pr_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try {
            $pdo->exec("ALTER TABLE password_reset_tokens ADD CONSTRAINT fk_pwd_reset_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE");
        } catch (Exception $e) { /* ya existe */ }
    } catch (Exception $e) { /* permisos u otro motivo: el helper deja de funcionar pero no rompe la app */ }
    $ensured = true;
}

function password_reset_create(string $email): bool
{
    global $pdo;
    _password_reset_ensure_table();

    try {
        $stmt = $pdo->prepare("SELECT id, username, email FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
    if (!$user) return false;

    $token   = bin2hex(random_bytes(32));
    $hash    = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + RESET_TOKEN_TTL_SECONDS);

    try {
        // Invalidar tokens activos previos del mismo usuario; evita
        // acumular tokens validos cuando el user solicita varios reset.
        $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
            ->execute([(int)$user['id']]);
        $stmt = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([(int)$user['id'], $hash, $expires]);
    } catch (Exception $e) {
        return false;
    }

    return _password_reset_send_email((string)$user['email'], (string)$user['username'], $token);
}

function password_reset_lookup(string $token): ?array
{
    global $pdo;
    if (strlen($token) !== 64) return null;
    _password_reset_ensure_table();

    try {
        $stmt = $pdo->prepare(
            'SELECT u.id, u.username, u.email, prt.id AS token_id
             FROM password_reset_tokens prt
             JOIN usuarios u ON u.id = prt.user_id
             WHERE prt.token_hash = ?
               AND prt.expires_at > NOW()
               AND prt.used_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function password_reset_consume(string $token, string $newPassword): bool
{
    global $pdo;
    if (strlen($newPassword) < 8) return false;

    $row = password_reset_lookup($token);
    if (!$row) return false;

    try {
        $pdo->beginTransaction();
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?')
            ->execute([$hash, (int)$row['id']]);
        $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = ?')
            ->execute([(int)$row['token_id']]);
        $pdo->commit();
    } catch (Exception $e) {
        try { $pdo->rollBack(); } catch (Exception $e2) {}
        return false;
    }

    // Compromiso de seguridad: tras un reset todas las sesiones persistentes
    // del usuario quedan invalidadas. Si el helper no esta cargado (caso
    // raro), seguimos adelante sin romper.
    if (function_exists('remember_me_clear_all_for_user')) {
        remember_me_clear_all_for_user((int)$row['id']);
    }

    return true;
}

function _password_reset_send_email(string $email, string $username, string $token): bool
{
    $resetLink = SITE_URL . '/reset-password.php?token=' . urlencode($token);
    $subject   = 'Restablecer tu contraseña en ' . SITE_NAME;
    $bodyHtml  = _password_reset_email_html($username, $resetLink);

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= 'From: ' . SITE_NAME . ' <noreply@rulethemando.com>' . "\r\n";

    return @mail($email, $subject, $bodyHtml, $headers);
}

function _password_reset_email_html(string $username, string $link): string
{
    $usernameSafe = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $linkSafe     = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    $siteName     = htmlspecialchars(SITE_NAME, ENT_QUOTES, 'UTF-8');
    $ttlMinutes   = (int)(RESET_TOKEN_TTL_SECONDS / 60);

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Restablecer contraseña — {$siteName}</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f1f5f9;">
    <tr>
      <td align="center" style="padding:40px 16px;">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 12px rgba(15,23,42,0.06);">
          <tr>
            <td bgcolor="#6366f1" style="background:linear-gradient(135deg,#6366f1 0%,#8b5cf6 100%);background-color:#6366f1;padding:36px 24px;text-align:center;">
              <h1 style="margin:0;font-size:24px;font-weight:700;color:#ffffff;letter-spacing:-0.4px;">{$siteName}</h1>
              <p style="margin:6px 0 0;font-size:14px;color:#e0e7ff;">Restablecer tu contraseña</p>
            </td>
          </tr>
          <tr>
            <td style="padding:36px 44px;">
              <h2 style="margin:0 0 12px;font-size:20px;font-weight:600;color:#111827;">Hola {$usernameSafe},</h2>
              <p style="margin:0 0 18px;font-size:16px;line-height:1.6;color:#4b5563;">
                Hemos recibido una solicitud para restablecer la contraseña de tu cuenta. Pulsa el botón de abajo para crear una nueva.
              </p>
              <table role="presentation" cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 24px;">
                <tr>
                  <td bgcolor="#6366f1" style="border-radius:10px;background:linear-gradient(135deg,#6366f1,#8b5cf6);background-color:#6366f1;">
                    <a href="{$linkSafe}" style="display:inline-block;padding:14px 36px;font-size:16px;font-weight:600;color:#ffffff;text-decoration:none;border-radius:10px;">
                      Restablecer contraseña
                    </a>
                  </td>
                </tr>
              </table>
              <p style="margin:0 0 6px;font-size:13px;color:#6b7280;text-align:center;">¿El botón no funciona? Copia esta URL:</p>
              <p style="margin:0 0 24px;font-size:12px;line-height:1.5;color:#4b5563;word-break:break-all;text-align:center;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px;">
                {$linkSafe}
              </p>
              <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 18px;">
              <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">
                <strong style="color:#374151;">Este enlace caduca en {$ttlMinutes} minutos.</strong><br>
                Si no has solicitado tú este cambio, ignora este correo. Tu contraseña no cambiará a menos que pulses el enlace.
              </p>
            </td>
          </tr>
          <tr>
            <td bgcolor="#f9fafb" style="background:#f9fafb;padding:18px 44px;text-align:center;border-top:1px solid #e5e7eb;">
              <p style="margin:0;font-size:12px;color:#6b7280;">&copy; {$siteName}</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
}
