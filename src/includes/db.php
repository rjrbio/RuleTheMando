<?php
// Helpers de base de datos.
//
// Centraliza el CREATE TABLE IF NOT EXISTS de las tablas auxiliares
// (valoraciones, favoritos, criticas, critica_likes) y sus claves
// foraneas con ON DELETE CASCADE. Antes este bloque estaba duplicado
// literalmente en index.php, games.php, game.php, favorites.php y
// admin.php; cualquier cambio de schema obligaba a editar 5 sitios.
//
// La funcion es idempotente: usa una bandera estatica para no repetir
// las queries DDL en la misma request, y los CREATE / ALTER llevan
// IF NOT EXISTS / try-catch para que sea seguro llamarla varias veces
// sin romper si las tablas o constraints ya existen.

function ensure_auxiliary_tables(): void
{
    global $pdo;
    static $done = false;
    if ($done) return;

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS valoraciones (
            user_id INT NOT NULL,
            game_id INT NOT NULL,
            rating TINYINT UNSIGNED NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, game_id),
            INDEX (game_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS favoritos (
            user_id INT NOT NULL,
            game_id INT NOT NULL,
            posicion INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, game_id),
            UNIQUE KEY uniq_user_pos (user_id, posicion),
            INDEX (user_id),
            INDEX (game_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS criticas (
            user_id INT NOT NULL,
            game_id INT NOT NULL,
            contenido TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (user_id, game_id),
            INDEX (game_id),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $pdo->exec("CREATE TABLE IF NOT EXISTS critica_likes (
            review_user_id INT NOT NULL,
            game_id INT NOT NULL,
            liker_user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (review_user_id, game_id, liker_user_id),
            INDEX (game_id),
            INDEX (review_user_id),
            INDEX (liker_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // FKs con ON DELETE CASCADE. Cada ALTER en su propio try porque
        // MySQL no soporta IF NOT EXISTS en ADD CONSTRAINT y queremos
        // que sea idempotente.
        $alters = [
            "ALTER TABLE criticas ADD CONSTRAINT fk_criticas_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE",
            "ALTER TABLE criticas ADD CONSTRAINT fk_criticas_game FOREIGN KEY (game_id) REFERENCES videojuegos(id) ON DELETE CASCADE",
            "ALTER TABLE critica_likes ADD CONSTRAINT fk_cl_review FOREIGN KEY (review_user_id, game_id) REFERENCES criticas(user_id, game_id) ON DELETE CASCADE",
            "ALTER TABLE critica_likes ADD CONSTRAINT fk_cl_liker FOREIGN KEY (liker_user_id) REFERENCES usuarios(id) ON DELETE CASCADE",
            "ALTER TABLE valoraciones ADD CONSTRAINT fk_val_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE",
            "ALTER TABLE valoraciones ADD CONSTRAINT fk_val_game FOREIGN KEY (game_id) REFERENCES videojuegos(id) ON DELETE CASCADE",
            "ALTER TABLE favoritos ADD CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE",
            "ALTER TABLE favoritos ADD CONSTRAINT fk_fav_game FOREIGN KEY (game_id) REFERENCES videojuegos(id) ON DELETE CASCADE",
        ];
        foreach ($alters as $sql) {
            try { $pdo->exec($sql); } catch (Exception $e) { /* ya existe */ }
        }
    } catch (Exception $e) {
        // Silenciar para no romper la vista publica si la BD esta en un
        // estado raro; las paginas que dependen de estas tablas mostraran
        // datos vacios pero no caeran.
    }

    $done = true;
}
