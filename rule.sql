-- Crear base de datos
USE rjrbio_rule;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Actualizar la tabla de usuarios para incluir verificación por email
ALTER TABLE usuarios 
ADD COLUMN email VARCHAR(255) UNIQUE,
ADD COLUMN email_verified BOOLEAN DEFAULT FALSE,
ADD COLUMN verification_token VARCHAR(255),
ADD COLUMN verification_expires TIMESTAMP NULL,
ADD COLUMN reset_token VARCHAR(255),
ADD COLUMN reset_expires TIMESTAMP NULL;

-- Actualizar el usuario admin para incluir email
UPDATE usuarios SET email = 'admin@rulethemando.com', email_verified = TRUE WHERE username = 'admin';

-- Crear tabla para tokens de verificación (opcional, para mayor control)
CREATE TABLE verification_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    type ENUM('email_verification', 'password_reset') NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_type (user_id, type)
);

-- Tabla de videojuegos
CREATE TABLE videojuegos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titulo VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE,
    descripcion TEXT,
    fecha_lanzamiento DATE,
    plataforma VARCHAR(100),
    genero VARCHAR(100),
    desarrollador VARCHAR(100),
    imagen VARCHAR(255),
    precio DECIMAL(10,2),
    es_futuro_lanzamiento BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Índices para mejorar búsquedas y filtros
CREATE INDEX idx_videojuegos_titulo ON videojuegos (titulo);
CREATE UNIQUE INDEX idx_videojuegos_slug ON videojuegos (slug);
CREATE INDEX idx_videojuegos_plataforma ON videojuegos (plataforma);
CREATE INDEX idx_videojuegos_genero ON videojuegos (genero);
CREATE INDEX idx_videojuegos_fecha ON videojuegos (fecha_lanzamiento);
CREATE INDEX idx_videojuegos_estado ON videojuegos (es_futuro_lanzamiento);

-- Insertar usuario admin por defecto (contraseña: admin123)
INSERT INTO usuarios (username, password, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insertar algunos videojuegos de ejemplo
INSERT INTO videojuegos (titulo, descripcion, fecha_lanzamiento, plataforma, genero, desarrollador, imagen, precio, es_futuro_lanzamiento) VALUES
('The Legend of Zelda: Breath of the Wild', 'Una aventura épica en un mundo abierto lleno de exploración y descubrimientos.', '2017-03-03', 'Nintendo Switch', 'Aventura', 'Nintendo EPD', 'zelda_botw.jpg', 59.99, FALSE),
('God of War', 'Kratos regresa en una nueva aventura nórdica junto a su hijo Atreus.', '2018-04-20', 'PlayStation 4', 'Acción', 'Santa Monica Studio', 'god_of_war.jpg', 39.99, FALSE),
('Cyberpunk 2077', 'Un RPG de mundo abierto ambientado en el futuro distópico de Night City.', '2020-12-10', 'PC', 'RPG', 'CD Projekt Red', 'cyberpunk_2077.jpg', 49.99, FALSE),
('The Elder Scrolls VI', 'La próxima gran aventura en el universo de Elder Scrolls está por llegar.', '2026-12-31', 'PC', 'RPG', 'Bethesda Game Studios', 'elder_scrolls_6.jpg', 69.99, TRUE);