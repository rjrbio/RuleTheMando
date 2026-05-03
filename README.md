# Rule The Mando
## Tu lista de videojuegos 

### Descripción
**Rule The Mando** es una plataforma web completa para descubrir, clasificar y gestionar tu colección de videojuegos. Accede a una base de datos con miles de juegos de los últimos 30 años, consulta próximos lanzamientos y crea tu propia lista personalizada de favoritos. Vota, comenta y comparte tu opinión sobre tus juegos favoritos en una comunidad de jugadores.

<img width="80%" alt="main" src="https://github.com/user-attachments/assets/b07f95d6-17eb-498d-9de9-897500c4b6da" />

### Principales características
- **Base de datos extensa**: Más de 30 años de videojuegos
- **Sistema de autenticación**: Registro y login con **Supabase**
- **Gestión de favoritos**: Crea y organiza tu TOP de juegos
- **Sistema de valoración**: Vota y lee críticas de otros usuarios
- **Fichas detalladas**: Información completa de cada juego

### Autenticación de usuario
Dispone de un sistema de creación de usuario mediante el 'BaaS' **Supabase**:
<img width="50%" alt="login" src="https://github.com/user-attachments/assets/134a94e6-6d0e-4352-8a27-1afa14a5a7cd" />

### Gestión de favoritos y valoración
Una vez con usuario creado puedes votar, criticar y añadir a favoritos tus juegos:
<img width="60%" alt="list" src="https://github.com/user-attachments/assets/78c4fd5f-43da-443b-844d-836691d6bb88" />

Así como ordenar tu propia lista de TOP Favoritos:<br>
<img width="60%" alt="favs" src="https://github.com/user-attachments/assets/00c565b8-3010-496d-af67-9820081dd593" />

### Fichas de juego
Cada juego tiene su propia ficha donde se puede ver una descripción, la nota media por los usuarios, las críticas escritas...
<img width="60%" alt="game" src="https://github.com/user-attachments/assets/d876807b-4180-4e82-a664-bf845e7ddccb" />

## Arquitectura

### Stack tecnológico
- **Frontend:** HTML5, CSS3, JavaScript (Vanilla JS)
- **Backend:** PHP
- **Base de datos:** Supabase (PostgreSQL)
- **Autenticación:** Supabase Auth
- **Servidor:** Apache/LAMP

### Estructura del proyecto
```
src/
├── index.php              # Página principal
├── login.php              # Sistema de autenticación
├── games.php              # Listado de juegos
├── game.php               # Ficha detallada del juego
├── favorites.php          # Gestión de favoritos
├── admin.php              # Panel administrativo
├── config.php             # Configuración de Supabase
├── supabase-config.php    # Conexión a Supabase
├── styles.css             # Estilos generales
├── animations.js          # Animaciones y JavaScript
├── helpers/               # Scripts de utilidad
└── migrations/            # Migraciones de base de datos
```

### Flujo de datos
1. **Interfaz de usuario** (HTML/CSS/JS) → Envía peticiones
2. **Servidor PHP** → Procesa la lógica de negocio
3. **Supabase** → Almacena/recupera datos y gestiona autenticación
4. **Base de datos PostgreSQL** → Persistencia de datos

### Características principales de arquitectura
- **Autenticación centralizada** en Supabase
- **Separación de responsabilidades** entre frontend y backend
- **API REST** para comunicación cliente-servidor
- **Migraciones SQL** versionadas para cambios de base de datos
- **Panel administrativo** para gestión de contenidos
