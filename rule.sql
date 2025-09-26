-- Crear base de datos
CREATE DATABASE rule_the_mando CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE rule_the_mando;

-- Tabla de usuarios
CREATE TABLE usuarios (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabla de videojuegos
CREATE TABLE videojuegos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    titulo VARCHAR(255) NOT NULL,
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

-- Insertar usuario admin por defecto (contraseña: admin123)
INSERT INTO usuarios (username, password, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insertar algunos videojuegos de ejemplo
INSERT INTO videojuegos (titulo, descripcion, fecha_lanzamiento, plataforma, genero, desarrollador, imagen, precio, es_futuro_lanzamiento) VALUES
('The Legend of Zelda: Breath of the Wild', 'Una aventura épica en un mundo abierto lleno de exploración y descubrimientos.', '2017-03-03', 'Nintendo Switch', 'Aventura', 'Nintendo EPD', 'zelda_botw.jpg', 59.99, FALSE),
('God of War', 'Kratos regresa en una nueva aventura nórdica junto a su hijo Atreus.', '2018-04-20', 'PlayStation 4', 'Acción', 'Santa Monica Studio', 'god_of_war.jpg', 39.99, FALSE),
('Cyberpunk 2077', 'Un RPG de mundo abierto ambientado en el futuro distópico de Night City.', '2020-12-10', 'PC', 'RPG', 'CD Projekt Red', 'cyberpunk_2077.jpg', 49.99, FALSE),
('The Elder Scrolls VI', 'La próxima gran aventura en el universo de Elder Scrolls está por llegar.', '2026-12-31', 'PC', 'RPG', 'Bethesda Game Studios', 'elder_scrolls_6.jpg', 69.99, TRUE);