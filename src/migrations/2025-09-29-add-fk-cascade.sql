-- RuleTheMando: Añadir claves foráneas ON DELETE CASCADE e inicializar tablas si faltan
-- Seguro para ejecutar varias veces (idempotente) en MySQL 8.x

-- Asegurar tablas base (definiciones coherentes con la app)
CREATE TABLE IF NOT EXISTS usuarios (
  id INT NOT NULL AUTO_INCREMENT,
  username VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  password VARCHAR(255),
  email_verified TINYINT(1) DEFAULT 0,
  role ENUM('user','admin') DEFAULT 'user',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS videojuegos (
  id INT NOT NULL AUTO_INCREMENT,
  titulo VARCHAR(255) NOT NULL,
  slug VARCHAR(255),
  descripcion TEXT NOT NULL,
  fecha_lanzamiento DATE,
  plataforma VARCHAR(255),
  genero VARCHAR(255),
  desarrollador VARCHAR(255),
  imagen VARCHAR(255),
  precio DECIMAL(10,2) NULL,
  es_futuro_lanzamiento TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS valoraciones (
  user_id INT NOT NULL,
  game_id INT NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, game_id),
  KEY idx_game (game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS favoritos (
  user_id INT NOT NULL,
  game_id INT NOT NULL,
  posicion INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, game_id),
  UNIQUE KEY uniq_user_pos (user_id, posicion),
  KEY idx_user (user_id),
  KEY idx_game (game_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS criticas (
  user_id INT NOT NULL,
  game_id INT NOT NULL,
  contenido TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, game_id),
  KEY idx_game (game_id),
  KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS critica_likes (
  review_user_id INT NOT NULL,
  game_id INT NOT NULL,
  liker_user_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (review_user_id, game_id, liker_user_id),
  KEY idx_game (game_id),
  KEY idx_review_user (review_user_id),
  KEY idx_liker (liker_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Helpers para ALTER condicionales
-- fk_criticas_user
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'criticas' AND CONSTRAINT_NAME = 'fk_criticas_user');
SET @sql := IF(@exists = 0,
  'ALTER TABLE criticas ADD CONSTRAINT fk_criticas_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- fk_criticas_game
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'criticas' AND CONSTRAINT_NAME = 'fk_criticas_game');
SET @sql := IF(@exists = 0,
  'ALTER TABLE criticas ADD CONSTRAINT fk_criticas_game FOREIGN KEY (game_id) REFERENCES videojuegos(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- fk_cl_review (compuesta hacia criticas)
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'critica_likes' AND CONSTRAINT_NAME = 'fk_cl_review');
SET @sql := IF(@exists = 0,
  'ALTER TABLE critica_likes ADD CONSTRAINT fk_cl_review FOREIGN KEY (review_user_id, game_id) REFERENCES criticas(user_id, game_id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- fk_cl_liker
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'critica_likes' AND CONSTRAINT_NAME = 'fk_cl_liker');
SET @sql := IF(@exists = 0,
  'ALTER TABLE critica_likes ADD CONSTRAINT fk_cl_liker FOREIGN KEY (liker_user_id) REFERENCES usuarios(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- fk_val_user
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'valoraciones' AND CONSTRAINT_NAME = 'fk_val_user');
SET @sql := IF(@exists = 0,
  'ALTER TABLE valoraciones ADD CONSTRAINT fk_val_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- fk_val_game
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'valoraciones' AND CONSTRAINT_NAME = 'fk_val_game');
SET @sql := IF(@exists = 0,
  'ALTER TABLE valoraciones ADD CONSTRAINT fk_val_game FOREIGN KEY (game_id) REFERENCES videojuegos(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- fk_fav_user
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'favoritos' AND CONSTRAINT_NAME = 'fk_fav_user');
SET @sql := IF(@exists = 0,
  'ALTER TABLE favoritos ADD CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- fk_fav_game
SET @exists := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
                WHERE CONSTRAINT_SCHEMA = DATABASE()
                  AND TABLE_NAME = 'favoritos' AND CONSTRAINT_NAME = 'fk_fav_game');
SET @sql := IF(@exists = 0,
  'ALTER TABLE favoritos ADD CONSTRAINT fk_fav_game FOREIGN KEY (game_id) REFERENCES videojuegos(id) ON DELETE CASCADE',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Nota: Si existen filas huérfanas, los ALTER pueden fallar.
-- Solución: limpiar datos huérfanos antes de ejecutar o eliminar manualmente esas filas.
