<?php
// Handlers POST/GET del panel admin. Incluido desde admin.php tras
// requireAdmin() y ensure_auxiliary_tables(). Comparte scope global
// con admin.php para leer/escribir $pdo, $message, $success,
// $openAddTab y $oldAdd.

// Procesar formulario de añadir juego
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_game'])) {
    csrf_check();
    $oldAdd = $_POST; // guardar valores enviados
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $fecha_lanzamiento = $_POST['fecha_lanzamiento'] ?? '';
    $plataforma = trim((string)($_POST['plataforma'] ?? ''));
    $genero = trim((string)($_POST['genero'] ?? ''));
    $desarrollador = trim((string)($_POST['desarrollador'] ?? ''));
    // El proyecto ya no usa precio; guardamos NULL siempre
    $precio = null;
    $es_futuro_lanzamiento = isset($_POST['es_futuro_lanzamiento']) ? 1 : 0;

    // Manejar subida de imagen
    $imagen = '';
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        $filename = $_FILES['imagen']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);

        // Validación por extensión
        if (!in_array(strtolower($filetype), $allowedExt)) {
            $message = 'Formato de imagen no válido. Usa JPG, PNG, GIF o WebP.';
        }

        // Validación por tamaño
        if (empty($message) && $_FILES['imagen']['size'] > $maxSize) {
            $message = 'La imagen supera el tamaño máximo de 5MB.';
        }

        // Validación por MIME real
        if (empty($message)) {
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
            $mime = $finfo ? finfo_file($finfo, $_FILES['imagen']['tmp_name']) : null;
            if ($finfo) { finfo_close($finfo); }
            if ($mime && !in_array($mime, $allowedMime)) {
                $message = 'El archivo no parece ser una imagen válida.';
            }
        }

        if (empty($message)) {
            $imagen = uniqid('', true) . '.' . strtolower($filetype);
            $upload_path = UPLOAD_PATH . $imagen;
            if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_path)) {
                $imagen = '';
                $message = 'Error al subir la imagen.';
            }
        }
    }

    // Validación mínima de campos requeridos
    if (empty($message)) {
        if ($titulo === '' || $descripcion === '' || empty($fecha_lanzamiento) || $plataforma === '' || $genero === '' || $desarrollador === '') {
            $message = 'Por favor, completa todos los campos obligatorios.';
        }
    }

    if (empty($message)) {
        $hasSlug = function_exists('hasColumn') ? hasColumn($pdo, 'videojuegos', 'slug') : false;
        $slug = $hasSlug ? slugify($titulo) : null;
        if ($hasSlug) {
            // evitar colisiones de slug
            $base = $slug; $i = 1;
            while (true) {
                $check = $pdo->prepare('SELECT COUNT(*) FROM videojuegos WHERE slug = ?');
                $check->execute([$slug]);
                if ($check->fetchColumn() == 0) break;
                $slug = $base . '-' . (++$i);
            }
        }

        if ($hasSlug) {
            $stmt = $pdo->prepare("INSERT INTO videojuegos (titulo, slug, descripcion, fecha_lanzamiento, plataforma, genero, desarrollador, imagen, precio, es_futuro_lanzamiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ok = $stmt->execute([$titulo, $slug, $descripcion, $fecha_lanzamiento, $plataforma, $genero, $desarrollador, $imagen, $precio, $es_futuro_lanzamiento]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO videojuegos (titulo, descripcion, fecha_lanzamiento, plataforma, genero, desarrollador, imagen, precio, es_futuro_lanzamiento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ok = $stmt->execute([$titulo, $descripcion, $fecha_lanzamiento, $plataforma, $genero, $desarrollador, $imagen, $precio, $es_futuro_lanzamiento]);
        }

        if ($ok) {
            $_SESSION['flash_message'] = 'Juego añadido exitosamente.';
            $_SESSION['flash_success'] = true;
            redirect('admin.php#manage-games');
        } else {
            $message = 'Error al añadir el juego.';
        }
    }

    // Si hay error, mostrar tab de alta y mantener valores
    if (!empty($message)) {
        $openAddTab = true;
    }
}

// Procesar eliminación de juego
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $game_id = intval($_GET['delete']);

    // Obtener información del juego para eliminar imagen
    $stmt = $pdo->prepare("SELECT imagen FROM videojuegos WHERE id = ?");
    $stmt->execute([$game_id]);
    $game = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($game && $game['imagen'] && file_exists(UPLOAD_PATH . $game['imagen'])) {
        unlink(UPLOAD_PATH . $game['imagen']);
    }

    $stmt = $pdo->prepare("DELETE FROM videojuegos WHERE id = ?");
    if ($stmt->execute([$game_id])) {
        $success = true;
        $message = 'Juego eliminado exitosamente.';
    } else {
        $message = 'Error al eliminar el juego.';
    }
}

// Procesar actualización de juego
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_game'])) {
    csrf_check();
    $game_id = intval($_POST['game_id'] ?? 0);
    if ($game_id > 0) {
        $titulo = trim((string)($_POST['titulo'] ?? ''));
        $descripcion = trim((string)($_POST['descripcion'] ?? ''));
        $fecha_lanzamiento = $_POST['fecha_lanzamiento'] ?? null;
        $plataforma = trim((string)($_POST['plataforma'] ?? ''));
        $genero = trim((string)($_POST['genero'] ?? ''));
        $desarrollador = trim((string)($_POST['desarrollador'] ?? ''));
    // El proyecto ya no usa precio; guardamos NULL siempre
    $precio = null;
        $es_futuro_lanzamiento = isset($_POST['es_futuro_lanzamiento']) ? 1 : 0;

        // Obtener imagen actual
        $stmt = $pdo->prepare('SELECT imagen FROM videojuegos WHERE id = ?');
        $stmt->execute([$game_id]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        $imagen = $current ? ($current['imagen'] ?? '') : '';

        // Manejar nueva imagen (opcional) — misma validación que en alta:
        // extensión + tamaño + MIME real con finfo + nombre seguro generado.
        if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
            $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            $filename = $_FILES['imagen']['name'];
            $filetype = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (!in_array($filetype, $allowedExt)) {
                $message = 'Formato de imagen no válido. Usa JPG, PNG, GIF o WebP.';
            } elseif ($_FILES['imagen']['size'] > $maxSize) {
                $message = 'La imagen supera el tamaño máximo de 5MB.';
            } else {
                $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
                $mime = $finfo ? finfo_file($finfo, $_FILES['imagen']['tmp_name']) : null;
                if ($finfo) { finfo_close($finfo); }
                if ($mime && !in_array($mime, $allowedMime)) {
                    $message = 'El archivo no parece ser una imagen válida.';
                } else {
                    $newName = uniqid('', true) . '.' . $filetype;
                    $upload_path = UPLOAD_PATH . $newName;
                    if (move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_path)) {
                        if ($imagen && file_exists(UPLOAD_PATH . $imagen)) {
                            @unlink(UPLOAD_PATH . $imagen);
                        }
                        $imagen = $newName;
                    } else {
                        $message = 'No se pudo subir la nueva imagen.';
                    }
                }
            }
        }

        if (empty($message)) {
            $hasSlug = function_exists('hasColumn') ? hasColumn($pdo, 'videojuegos', 'slug') : false;
            $slugSet = '';
            $params = [$titulo, $descripcion, $fecha_lanzamiento, $plataforma, $genero, $desarrollador, $imagen, $precio, $es_futuro_lanzamiento, $game_id];
            if ($hasSlug) {
                $newSlug = slugify($titulo);
                // resolver colisiones si el slug cambia
                $check = $pdo->prepare('SELECT id FROM videojuegos WHERE slug = ? AND id <> ?');
                $candidate = $newSlug; $i = 1;
                while (true) {
                    $check->execute([$candidate, $game_id]);
                    if (!$check->fetch(PDO::FETCH_ASSOC)) break;
                    $candidate = $newSlug . '-' . (++$i);
                }
                $slugSet = ', slug=?';
                // mover slug a penúltima posición, antes de id
                $params = [$titulo, $descripcion, $fecha_lanzamiento, $plataforma, $genero, $desarrollador, $imagen, $precio, $es_futuro_lanzamiento, $candidate, $game_id];
            }
            $stmt = $pdo->prepare('UPDATE videojuegos SET titulo=?, descripcion=?, fecha_lanzamiento=?, plataforma=?, genero=?, desarrollador=?, imagen=?, precio=?, es_futuro_lanzamiento=?' . $slugSet . ' WHERE id=?');
            $ok = $stmt->execute($params);
            if ($ok) {
                // Mensaje flash y redirección (PRG) a gestionar juegos para evitar reabrir modal
                $_SESSION['flash_message'] = 'Juego actualizado correctamente.';
                $_SESSION['flash_success'] = true;
                redirect('admin.php#manage-games');
            } else {
                $success = false;
                $message = 'No se pudo actualizar el juego.';
            }
        }
    }
}

// Acciones de gestión de usuarios (solo admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_action'])) {
    csrf_check();
    $action = $_POST['user_action'];
    $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;

    try {
        if ($action === 'verify_user' && $userId > 0) {
            $stmt = $pdo->prepare('UPDATE usuarios SET email_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?');
            $ok = $stmt->execute([$userId]);
            $success = $ok;
            $message = $ok ? 'Usuario marcado como verificado.' : 'No se pudo marcar el usuario como verificado.';
        } elseif ($action === 'unverify_user' && $userId > 0) {
            $stmt = $pdo->prepare('UPDATE usuarios SET email_verified = 0 WHERE id = ?');
            $ok = $stmt->execute([$userId]);
            $success = $ok;
            $message = $ok ? 'Usuario marcado como no verificado.' : 'No se pudo actualizar el estado de verificación.';
        } elseif ($action === 'resend_verification' && $userId > 0) {
            $stmt = $pdo->prepare('SELECT email FROM usuarios WHERE id = ?');
            $stmt->execute([$userId]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u && !empty($u['email'])) {
                $resp = sendSupabaseVerificationEmail($u['email'], SITE_URL . '/verify-supabase.php');
                $success = !empty($resp['success']);
                $message = $success ? 'Email de verificación reenviado (vía Supabase).' : 'No se pudo reenviar el email de verificación.';
            } else {
                $success = false;
                $message = 'El usuario no tiene email asignado.';
            }
        } elseif ($action === 'reset_password' && $userId > 0) {
            // Generar contraseña temporal segura
            $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789@#$%';
            $temp = '';
            for ($i = 0; $i < 12; $i++) {
                $temp .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $hash = password_hash($temp, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE usuarios SET password = ? WHERE id = ?');
            $ok = $stmt->execute([$hash, $userId]);
            $success = $ok;
            if ($ok) {
                $message = 'Contraseña reseteada. Temporal: ' . htmlspecialchars($temp) . ' (cámbiala tras el primer login)';
            } else {
                $message = 'No se pudo resetear la contraseña.';
            }
        } elseif ($action === 'set_role' && $userId > 0 && isset($_POST['role'])) {
            $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
            $stmt = $pdo->prepare('UPDATE usuarios SET role = ? WHERE id = ?');
            $ok = $stmt->execute([$role, $userId]);
            $success = $ok;
            $message = $ok ? 'Rol actualizado.' : 'No se pudo actualizar el rol.';
        }
    } catch (Exception $e) {
        $success = false;
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
    }
}

// Acciones de gestión de críticas (solo admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_action'])) {
    csrf_check();
    $action = $_POST['review_action'];
    $uId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $gId = isset($_POST['game_id']) ? intval($_POST['game_id']) : 0;
    try {
        if ($action === 'delete' && $uId > 0 && $gId > 0) {
            // eliminar crítica y sus likes asociados
            $stmt = $pdo->prepare('DELETE FROM criticas WHERE user_id=? AND game_id=?');
            $ok = $stmt->execute([$uId, $gId]);
            $pdo->prepare('DELETE FROM critica_likes WHERE review_user_id=? AND game_id=?')->execute([$uId, $gId]);
            $success = $ok; $message = $ok ? 'Crítica eliminada.' : 'No se pudo eliminar la crítica.';
        } elseif ($action === 'update' && $uId > 0 && $gId > 0) {
            $contenido = trim($_POST['contenido'] ?? '');
            if ($contenido === '') {
                $stmt = $pdo->prepare('DELETE FROM criticas WHERE user_id=? AND game_id=?');
                $ok = $stmt->execute([$uId, $gId]);
                $pdo->prepare('DELETE FROM critica_likes WHERE review_user_id=? AND game_id=?')->execute([$uId, $gId]);
                $success = $ok; $message = $ok ? 'Crítica eliminada.' : 'No se pudo eliminar la crítica.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO criticas (user_id, game_id, contenido, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())
                                       ON DUPLICATE KEY UPDATE contenido=VALUES(contenido), updated_at=NOW()');
                $ok = $stmt->execute([$uId, $gId, $contenido]);
                $success = $ok; $message = $ok ? 'Crítica actualizada.' : 'No se pudo actualizar la crítica.';
            }
        }
    } catch (Exception $e) { $success = false; $message = 'Error: ' . htmlspecialchars($e->getMessage()); }
}
