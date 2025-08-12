# Draftosaurus I.G.P.D.
## Sistema Informático de Gestión de Partidas para Draftosaurus

Una aplicación web local (Single Page Application) para gestionar partidas del juego de mesa Draftosaurus, desarrollada como proyecto integrador para el ITI CETP.

### 🎯 Características Principales

- **Modo Juego Digital**: Jugar partidas completas desde la aplicación
- **Modo Seguimiento**: Herramienta auxiliar para calcular puntos durante partidas físicas
- **Modo Administrador**: Gestión de usuarios registrados
- **Single Page Application**: Navegación fluida sin recargas
- **Diseño Responsivo**: Adaptado para móviles, tablets y escritorio
- **Sistema de Autenticación**: Registro y login de usuarios
- **Base de Datos Completa**: Persistencia de partidas y estadísticas

### 🛠️ Tecnologías Utilizadas

- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Backend**: PHP 8.x
- **Base de Datos**: MySQL/MariaDB
- **Entorno de Desarrollo**: XAMPP 8.x
- **Control de Versiones**: Git

### 📁 Estructura del Proyecto

draftosaurus/
├── index.html                    # Tu archivo actual
├── 404.html                      # NUEVO - Error 404
├── 403.html                      # NUEVO - Error 403
├── 500.html                      # NUEVO - Error 500
├── .htaccess                      # Tu archivo actual
│
├── css/                          # Tus archivos actuales
│   ├── styles.css
│   └── tablero.css
│
├── js/                           
│   ├── app.js                    # ACTUALIZAR (agregar al final)
│   ├── game-engine.js            # REEMPLAZAR completo
│   ├── tablero.js                # Tu archivo actual
│   ├── utils.js                  # Tu archivo actual
│   └── advanced-features.js      # NUEVO archivo
│
├── img/                          # Tus archivos actuales
│
├── api/
│   ├── config/
│   │   └── config.php            # Tu archivo actual
│   ├── classes/
│   │   └── Database.php          # Tu archivo actual
│   ├── models/
│   │   └── User.php              # Tu archivo actual
│   ├── auth/                     # Tus archivos actuales
│   ├── game/                     # Tus archivos actuales
│   │   ├── create.php            # + AGREGAR save.php y load.php
│   │   ├── save.php              # NUEVO
│   │   └── load.php              # NUEVO
│   ├── admin/                    # NUEVA carpeta
│   │   ├── stats.php             # NUEVO
│   │   ├── users.php             # NUEVO
│   │   └── games.php             # NUEVO
│   ├── user/                     # NUEVA carpeta
│   │   ├── profile.php           # NUEVO
│   │   ├── password.php          # NUEVO
│   │   └── stats.php             # NUEVO
│   ├── utils/                    # NUEVA carpeta
│   │   └── random-avatar.php     # NUEVO
│   ├── analytics/                # NUEVA carpeta
│   │   └── session.php           # NUEVO
│   └── install/
│       └── database.sql          # Tu archivo actual
│
├── uploads/                      # Tu carpeta actual
└── logs/                         # Tu carpeta actual
```

### 🚀 Instalación

#### Requisitos Previos
- XAMPP 8.x o servidor con PHP 8.x y MySQL/MariaDB
- Navegador web moderno
- Git (opcional)

#### Pasos de Instalación

1. **Clonar o descargar el proyecto**
   ```bash
   git clone [url-del-repositorio]
   cd draftosaurus
   ```

2. **Configurar el entorno**
   - Iniciar XAMPP (Apache y MySQL)
   - Copiar el proyecto a la carpeta `htdocs` de XAMPP

3. **Configurar la base de datos**
   - Crear la base de datos ejecutando `api/install/database.sql`
   - Ajustar la configuración en `api/config/config.php` si es necesario

4. **Configurar permisos**
   ```bash
   chmod 755 uploads/
   chmod 755 logs/
   ```

5. **Acceder a la aplicación**
   - Abrir navegador en `http://localhost/draftosaurus`

#### Usuario Administrador por Defecto
- **Usuario**: admin
- **Contraseña**: password

### 🎮 Cómo Usar

#### Modo Juego Digital
1. Crear nueva partida desde el menú principal
2. Configurar número de jugadores (2-5)
3. Seguir las reglas del juego original Draftosaurus
4. La aplicación calcula puntos automáticamente

#### Modo Seguimiento
1. Seleccionar "Modo Seguimiento" desde el menú
2. Usar como herramienta auxiliar durante partidas físicas
3. Registrar movimientos para validación y cálculo de puntos

#### Administración
1. Iniciar sesión con cuenta de administrador
2. Gestionar usuarios registrados
3. Ver estadísticas de partidas

### 🎲 Reglas del Juego

Draftosaurus es un juego de draft y colocación donde los jugadores construyen su parque de dinosaurios:

#### Preparación
- Cada jugador toma un tablero de parque
- 60 dinosaurios de 6 especies diferentes en una bolsa
- 1 dado de colocación con restricciones

#### Desarrollo
- **2 rondas** de **6 turnos** cada una
- Cada turno: tomar dinosaurios → lanzar dado → elegir y colocar → pasar dinosaurios
- El dado impone restricciones de colocación para todos excepto quien lo lanza

#### Recintos y Puntuación
1. **Bosque de la Semejanza**: Solo misma especie, puntos por cantidad
2. **Prado de la Diferencia**: Solo especies distintas, puntos por variedad
3. **Pradera del Amor**: 5 puntos por pareja de misma especie
4. **Trío Frondoso**: 7 puntos si hay exactamente 3 dinosaurios
5. **Rey de la Selva**: 7 puntos si tienes más de esa especie que otros
6. **Isla Solitaria**: 7 puntos si es único de su especie en tu parque
7. **Río**: 1 punto por dinosaurio (colocación obligatoria si no hay lugar válido)

#### Bonificaciones
- +1 punto por cada recinto con al menos 1 T-Rex
- +1 punto por cada dinosaurio en el río

### 🏗️ Arquitectura

#### Frontend (SPA)
- **Arquitectura de componentes**: Componentes dinámicos que aparecen/desaparecen
- **Gestión de estado**: Estado global de la aplicación en JavaScript
- **Fondos dinámicos**: 3 fondos que cambian según el contexto
- **Comunicación asíncrona**: Fetch API para comunicación con backend

#### Backend (PHP)
- **Arquitectura en 3 capas**: Presentación, Negocio, Datos
- **Patrón MVC**: Controladores, Modelos y Vistas (JSON)
- **Programación Orientada a Objetos**: Clases para entidades principales
- **API RESTful**: Endpoints para operaciones CRUD

#### Base de Datos
- **Modelo relacional normalizado**: Hasta 3FN
- **Integridad referencial**: Foreign keys y constraints
- **Triggers**: Para mantener estadísticas actualizadas
- **Vistas**: Para consultas complejas optimizadas

### 📊 API Endpoints

#### Autenticación
- `POST /api/auth/login.php` - Iniciar sesión
- `POST /api/auth/register.php` - Registrar usuario
- `POST /api/auth/logout.php` - Cerrar sesión
- `GET /api/auth/session.php` - Verificar sesión

#### Partidas
- `POST /api/game/create.php` - Crear partida
- `POST /api/game/join.php` - Unirse a partida
- `POST /api/game/move.php` - Realizar movimiento
- `GET /api/game/status.php` - Estado de partida

#### Administración
- `GET /api/admin/users.php` - Listar usuarios
- `GET /api/admin/stats.php` - Estadísticas generales

### 🧪 Testing

Para probar la aplicación:

1. **Funcionalidad básica**:
   - Navegación entre pantallas
   - Registro e inicio de sesión
   - Creación de partidas

2. **Modo seguimiento**:
   - Validación de reglas
   - Cálculo de puntuaciones
   - Persistencia de datos

3. **Responsividad**:
   - Probar en diferentes dispositivos
   - Verificar adaptación de fondos

### 👥 Contribución

Este proyecto es parte del curriculum del ITI CETP. Para contribuir:

1. Fork del repositorio
2. Crear rama para nueva funcionalidad
3. Commit de cambios
4. Pull request con descripción detallada

### 📝 Documentación Adicional

- [Reglas completas de Draftosaurus](docs/reglas-completas.md)
- [Documentación de la API](docs/api-reference.md)
- [Guía de desarrollo](docs/development-guide.md)

### 📄 Licencia

Proyecto educativo para el ITI CETP - Instituto Tecnológico de Informática.

### ✨ Créditos

**Equipo de Desarrollo**: [Nombre del grupo]
**Institución**: ITI CETP - ANEP UTU
**Año**: 2025

---

## 🔧 Solución de Problemas

### Problemas Comunes

#### Error de conexión a base de datos
- Verificar que XAMPP esté ejecutándose
- Revisar configuración en `api/config/config.php`
- Verificar que la base de datos existe

#### Imágenes no se muestran
- Verificar que las imágenes están en la carpeta `img/`
- Revisar rutas en CSS
- Verificar permisos de archivos

#### Problemas de sesión
- Verificar configuración de PHP para sesiones
- Limpiar cookies del navegador
- Revisar logs en `logs/error_[fecha].log`