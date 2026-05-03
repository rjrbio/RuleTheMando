<?php
// Sistema de migraciones SQL versionadas.
//
// Como funciona:
//   - Los archivos SQL viven en src/migrations/, uno por migracion.
//     Convencion de nombre: YYYY-MM-DD-description.sql (ordenacion
//     alfabetica = orden cronologico).
//   - La tabla schema_migrations guarda que migraciones ya se han
//     aplicado en esta BD. La clave es el nombre del archivo sin .sql.
//   - migrations_pending() devuelve los archivos que aun no se han
//     aplicado. migrations_run() los ejecuta uno a uno y los inserta
//     en schema_migrations en cuanto cada uno sale OK.
//
// Cada migration debe ser idempotente (CREATE TABLE IF NOT EXISTS,
// ALTER ... wrapped en sub-procedures o try-catch en el SQL si MySQL
// se queja al re-aplicar). No usamos transacciones porque la mayoria
// de DDL en MySQL es auto-commit.
//
// Si una migration falla, las posteriores NO se ejecutan: hay que
// arreglar el SQL manualmente y volver a llamar.
//
// El runner NO sustituye a ensure_auxiliary_tables() — esa funcion
// crea on-demand las tablas auxiliares al primer uso. El sistema de
// migrations es complementario: para cambios futuros de schema y para
// poder reproducir el estado de la BD desde cero en un entorno limpio.

const MIGRATIONS_DIR = __DIR__ . '/../migrations';

function _migrations_ensure_table(): void
{
    global $pdo;
    static $ensured = false;
    if ($ensured) return;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(128) PRIMARY KEY,
            applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Exception $e) { /* permisos o BD readonly */ }
    $ensured = true;
}

function migrations_applied_versions(): array
{
    global $pdo;
    _migrations_ensure_table();
    $applied = [];
    try {
        $stmt = $pdo->query('SELECT version, applied_at FROM schema_migrations ORDER BY version ASC');
        $applied = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) { /* tabla no existe o readonly */ }
    return $applied;
}

function migrations_pending(): array
{
    $appliedRows = migrations_applied_versions();
    $appliedSet = [];
    foreach ($appliedRows as $r) { $appliedSet[$r['version']] = true; }

    $pending = [];
    $files = glob(MIGRATIONS_DIR . '/*.sql') ?: [];
    sort($files);
    foreach ($files as $file) {
        $version = basename($file, '.sql');
        if (!isset($appliedSet[$version])) {
            $pending[] = ['version' => $version, 'file' => $file];
        }
    }
    return $pending;
}

function migrations_run(): array
{
    global $pdo;
    _migrations_ensure_table();
    $pending = migrations_pending();
    $results = [];

    foreach ($pending as $m) {
        $sql = @file_get_contents($m['file']);
        if ($sql === false) {
            $results[] = ['version' => $m['version'], 'ok' => false, 'error' => 'No se pudo leer el archivo.'];
            break;
        }
        // Saltar archivos vacios marcandolos como aplicados (asi no se
        // reintentan en cada llamada). Util para placeholders.
        if (trim($sql) === '') {
            try {
                $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')
                    ->execute([$m['version']]);
                $results[] = ['version' => $m['version'], 'ok' => true, 'note' => 'Vacio (marcado).'];
                continue;
            } catch (Exception $e) {
                $results[] = ['version' => $m['version'], 'ok' => false, 'error' => $e->getMessage()];
                break;
            }
        }
        try {
            $pdo->exec($sql);
            $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)')
                ->execute([$m['version']]);
            $results[] = ['version' => $m['version'], 'ok' => true];
        } catch (Exception $e) {
            $results[] = ['version' => $m['version'], 'ok' => false, 'error' => $e->getMessage()];
            break; // detener cadena tras el primer fallo
        }
    }
    return $results;
}
