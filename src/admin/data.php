<?php
// Cargas de datos para las vistas del panel admin. Incluido desde
// admin.php tras los handlers (que pueden haber redirigido tras
// un POST exitoso).

// Obtener todos los juegos para la tabla
$stmt = $pdo->prepare("SELECT * FROM videojuegos ORDER BY created_at DESC");
$stmt->execute();
$juegos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM videojuegos");
$stmt->execute();
$totalJuegos = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM videojuegos WHERE es_futuro_lanzamiento = TRUE");
$stmt->execute();
$futurosLanzamientos = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios");
$stmt->execute();
$totalUsuarios = $stmt->fetchColumn();

// Obtener usuarios para gestión
$stmt = $pdo->prepare('SELECT id, username, email, email_verified, role, created_at FROM usuarios ORDER BY created_at DESC');
$stmt->execute();
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cargar críticas con datos de usuario y juego (filtro opcional por búsqueda)
$reviewQ = isset($_GET['review_q']) ? trim($_GET['review_q']) : '';
$reviews = [];
try {
    $sql = 'SELECT c.user_id, c.game_id, c.contenido, c.updated_at, u.username, v.titulo,
                   (SELECT rating FROM valoraciones WHERE user_id=c.user_id AND game_id=c.game_id) as rating,
                   (SELECT COUNT(*) FROM critica_likes cl WHERE cl.review_user_id = c.user_id AND cl.game_id = c.game_id) AS likes_count
            FROM criticas c
            JOIN usuarios u ON u.id = c.user_id
            JOIN videojuegos v ON v.id = c.game_id';
    $params = [];
    if ($reviewQ !== '') {
        $sql .= ' WHERE u.username LIKE ? OR v.titulo LIKE ?';
        $like = '%' . $reviewQ . '%';
        $params = [$like, $like];
    }
    $sql .= ' ORDER BY c.updated_at DESC';
    $q = $pdo->prepare($sql);
    $q->execute($params);
    $reviews = $q->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { /* noop */ }
