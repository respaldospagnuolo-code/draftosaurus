# Estructura del Proyecto Draftosaurus I.G.P.D.

## 📁 Estructura de Directorios

```
draftosaurus/
├── 📄 index.html                    # Página principal (SPA)
├── 📄 .htaccess                     # Configuración de Apache
├── 📄 README.md                     # Documentación principal
├── 📄 ESTRUCTURA_PROYECTO.md        # Este archivo
│
├── 📁 css/                          # Hojas de estilo
│   ├── 📄 styles.css                # Estilos principales
│   ├── 📄 tablero.css               # Estilos del tablero de juego
│   └── 📄 perfil_usuario.css        # Estilos del perfil de usuario
│
├── 📁 js/                           # Scripts JavaScript
│   ├── 📄 app.js                    # Lógica principal de la aplicación
│   ├── 📄 tablero.js                # Lógica del tablero de juego
│   ├── 📄 game-engine.js            # Motor del juego
│   └── 📄 utils.js                  # Utilidades generales
│
├── 📁 img/                          # Recursos gráficos
│   ├── 📄 Fondo_inicial.jpg         # Fondo de la pantalla principal
│   ├── 📄 fondo-base.jpg            # Fondo de interfaces
│   ├── 📄 mapa_desktop.jpg          # Fondo del tablero (escritorio)
│   ├── 📄 mapa_mobile.jpg           # Fondo del tablero (móvil)
│   ├── 📄 mapa_tablet.jpg           # Fondo del tablero (tablet)
│   ├── 📄 Logo-draftosaurus.png     # Logo principal
│   ├── 📄 icono-salir.svg           # Icono de salir
│   ├── 📄 siguiente.svg             # Icono siguiente
│   ├── 📄 marco_usuario.png         # Marco para foto de usuario
│   │
│   ├── 📁 dinosaurios/              # Imágenes de dinosaurios
│   │   ├── 📄 dino-1-perfil.png     # Dinosaurio verde (menú)
│   │   ├── 📄 dino-1-arriba.png     # Dinosaurio verde (tablero)
│   │   ├── 📄 dino-2-perfil.png     # Dinosaurio azul (menú)
│   │   ├── 📄 dino-2-arriba.png     # Dinosaurio azul (tablero)
│   │   ├── 📄 dino-3-perfil.png     # Dinosaurio amarillo (menú)
│   │   ├── 📄 dino-3-arriba.png     # Dinosaurio amarillo (tablero)
│   │   ├── 📄 dino-4-perfil.png     # Dinosaurio rojo (menú)
│   │   ├── 📄 dino-4-arriba.png     # Dinosaurio rojo (tablero)
│   │   ├── 📄 dino-5-perfil.png     # Dinosaurio naranja (menú)
│   │   ├── 📄 dino-5-arriba.png     # Dinosaurio naranja (tablero)
│   │   ├── 📄 dino-6-perfil.png     # Dinosaurio rosa (menú)
│   │   └── 📄 dino-6-arriba.png     # Dinosaurio rosa (tablero)
│   │
│   ├── 📁 dados/                    # Caras del dado
│   │   ├── 📄 cara-dado-1.png       # Cara 1 del dado
│   │   ├── 📄 cara-dado-2.png       # Cara 2 del dado
│   │   ├── 📄 cara-dado-3.png       # Cara 3 del dado
│   │   ├── 📄 cara-dado-4.png       # Cara 4 del dado
│   │   ├── 📄 cara-dado-5.png       # Cara 5 del dado
│   │   └── 📄 cara-dado-6.png       # Cara 6 del dado
│   │
│   └── 📁 iconos/                   # Iconos de la interfaz
│       ├── 📄 icono_partidas-ganadas.png    # Icono de trofeo
│       ├── 📄 icono_mapa.png               # Icono de mapa
│       ├── 📄 icono_informacion.png        # Icono de información
│       ├── 📄 icono_datos_fisica.png       # Icono de física
│       ├── 📄 icono_mail.png               # Icono de email
│       ├── 📄 icono_contrasena.png         # Icono de contraseña
│       ├── 📄 icono_intercambio-dinos.png  # Icono de intercambio
│       ├── 📄 icono_ganador.png            # Icono de ganador
│       ├── 📄 foto_usuario-1.png           # Foto de usuario ejemplo
│       ├── 📄 foto_usuario-2.png           # Foto de usuario ejemplo
│       ├── 📄 bandera-en.png               # Bandera inglés
│       └── 📄 bandera-uruguay.png          # Bandera Uruguay
│
├── 📁 api/                          # Backend PHP
│   ├── 📁 config/                   # Configuración
│   │   └── 📄 config.php            # Configuración general
│   │
│   ├── 📁 classes/                  # Clases base
│   │   ├── 📄 Database.php          # Clase de base de datos
│   │   ├── 📄 Session.php           # Gestión de sesiones
│   │   └── 📄 Validator.php         # Validaciones
│   │
│   ├── 📁 models/                   # Modelos de datos
│   │   ├── 📄 User.php              # Modelo de usuario
│   │   ├── 📄 Game.php              # Modelo de partida
│   │   ├── 📄 Player.php            # Modelo de jugador
│   │   ├── 📄 Dinosaur.php          # Modelo de dinosaurio
│   │   └── 📄 Enclosure.php         # Modelo de recinto
│   │
│   ├── 📁 controllers/              # Controladores
│   │   ├── 📄 AuthController.php    # Controlador de autenticación
│   │   ├── 📄 GameController.php    # Controlador de partidas
│   │   ├── 📄 UserController.php    # Controlador de usuarios
│   │   └── 📄 AdminController.php   # Controlador de administración
│   │
│   ├── 📁 auth/                     # APIs de autenticación
│   │   ├── 📄 login.php             # API de login
│   │   ├── 📄 register.php          # API de registro
│   │   ├── 📄 logout.php            # API de logout
│   │   └── 📄 session.php           # Verificación de sesión
│   │
│   ├── 📁 game/                     # APIs de juego
│   │   ├── 📄 create.php            # Crear partida
│   │   ├── 📄 join.php              # Unirse a partida
│   │   ├── 📄 move.php              # Realizar movimiento
│   │   ├── 📄 status.php            # Estado de partida
│   │   └── 📄 finish.php            # Finalizar partida
│   │
│   ├── 📁 admin/                    # APIs de administración
│   │   ├── 📄 users.php             # Gestión de usuarios
│   │   ├── 📄 games.php             # Gestión de partidas
│   │   └── 📄 stats.php             # Estadísticas
│   │
│   ├── 📁 utils/                    # Utilidades PHP
│   │   ├── 📄 GameEngine.php        # Motor del juego
│   │   ├── 📄 ScoreCalculator.php   # Calculadora de puntos
│   │   └── 📄 FileUpload.php        # Subida de archivos
│   │
│   └── 📁 install/                  # Scripts de instalación
│       ├── 📄 install.php           # Script de instalación
│       └── 📄 database.sql          # Estructura de BD
│
├── 📁 uploads/                      # Archivos subidos
│   ├── 📁 profiles/                 # Fotos de perfil
│   └── 📁 temp/                     # Archivos temporales
│
└── 📁 logs/                         # Archivos de log
    ├── 📄 error_YYYY-MM-DD.log      # Logs de errores
    ├── 📄 access_YYYY-MM-DD.log     # Logs de acceso
    └── 📄 game_YYYY-MM-DD.log       # Logs de partidas
```

## 🔧 Archivos Principales

### Frontend

#### `index.html`
- **Función**: Página principal de la Single Page Application
- **Componentes**: Navegación, formularios, pantallas dinámicas
- **Fondos**: Maneja 3 fondos diferentes según el contexto

#### `css/styles.css`
- **Función**: Estilos principales de la aplicación
- **Incluye**: Navegación, formularios, botones, responsive design
- **Fuentes**: Oswald (títulos), Ubuntu (texto)

#### `js/app.js`
- **Función**: Lógica principal de la SPA
- **Características**: Gestión de estado, navegación, comunicación con API
- **Patrones**: Clase principal DraftosaurusApp, métodos async/await

### Backend

#### `api/config/config.php`
- **Función**: Configuración central del sistema
- **Define**: Constantes de BD, seguridad, rutas, configuración de juego
- **Incluye**: Funciones utilitarias, autoloader, manejo de errores

#### `api/classes/Database.php`
- **Función**: Clase singleton para gestión de BD
- **Características**: Conexión PDO, métodos CRUD, transacciones
- **Patrones**: Singleton, preparación de consultas

#### `api/models/User.php`
- **Función**: Modelo completo de usuario
- **Características**: Autenticación, validaciones, estadísticas
- **Seguridad**: Hash de contraseñas, bloqueo por intentos

### Base de Datos

#### `api/install/database.sql`
- **Función**: Estructura completa de la base de datos
- **Tablas**: usuarios, partidas, dinosaurios, recintos, logs
- **Características**: Constraints, triggers, vistas, índices

## 🎯 Flujo de la Aplicación

### 1. Carga Inicial
```
index.html → css/styles.css → js/app.js → api/auth/session.php
```

### 2. Autenticación
```
Formulario → js/app.js → api/auth/login.php → Session → Redirección
```

### 3. Crear Partida
```
Formulario → js/app.js → api/game/create.php → Tablero de Juego
```

### 4. Modo Seguimiento
```
Selección → js/tablero.js → api/game/move.php → Cálculo de Puntos
```

## 🔒 Características de Seguridad

### Frontend
- Validación dual (client-side y server-side)
- Sanitización de inputs
- Headers de seguridad
- Protección contra XSS

### Backend
- Consultas preparadas (SQL injection prevention)
- Hash de contraseñas con bcrypt
- Bloqueo por intentos de login
- Validación de sesiones
- Logs de seguridad

### Base de Datos
- Constraints y foreign keys
- Triggers para integridad
- Índices optimizados
- Backup automático

## 📱 Características Responsive

### Breakpoints
- **Móvil**: < 768px
- **Tablet**: 768px - 1024px  
- **Escritorio**: > 1024px

### Adaptaciones
- Fondos específicos por dispositivo
- Menús adaptables
- Touch-friendly en móviles
- Optimización de rendimiento

## 🎮 Arquitectura del Juego

### Frontend
```
app.js → tablero.js → game-engine.js → API
```

### Backend
```
GameController → GameEngine → ScoreCalculator → Database
```

### Flujo de Datos
1. **Input del Usuario** → Validación Frontend
2. **API Call** → Validación Backend
3. **Procesamiento** → GameEngine
4. **Persistencia** → Database
5. **Response** → Frontend Update

## 📊 Sistema de Logging

### Tipos de Logs
- **Errores**: `logs/error_YYYY-MM-DD.log`
- **Acceso**: `logs/access_YYYY-MM-DD.log`
- **Juego**: `logs/game_YYYY-MM-DD.log`

### Información Registrada
- Timestamp
- Nivel de error
- Mensaje descriptivo
- Contexto (usuario, IP, etc.)
- Stack trace (en desarrollo)

## 🚀 Optimizaciones

### Performance
- Compresión GZIP
- Cache de recursos estáticos
- Minificación de CSS/JS
- Optimización de imágenes

### SEO
- Metadatos apropiados
- Estructura semántica
- URLs amigables
- Sitemap.xml

### Accesibilidad
- Etiquetas ARIA
- Navegación por teclado
- Contraste de colores
- Lectores de pantalla

Esta estructura está diseñada para ser escalable, mantenible y seguir las mejores prácticas de desarrollo web moderno.