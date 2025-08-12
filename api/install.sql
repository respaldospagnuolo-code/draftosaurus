-- Base de datos para Draftosaurus I.G.P.D.
-- Sistema Informático de Gestión de Partidas para Draftosaurus

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- Crear base de datos
CREATE DATABASE IF NOT EXISTS `draftosaurus_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `draftosaurus_db`;

-- --------------------------------------------------------

-- Tabla de usuarios
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `fecha_registro` datetime DEFAULT CURRENT_TIMESTAMP,
  `ultimo_acceso` datetime NULL,
  `role` enum('admin','player') DEFAULT 'player',
  `activo` tinyint(1) DEFAULT 1,
  `intentos_login` int(11) DEFAULT 0,
  `bloqueado_hasta` datetime NULL,
  `token_recuperacion` varchar(64) NULL,
  `foto_perfil` varchar(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_username` (`username`),
  KEY `idx_email` (`email`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabla de partidas
CREATE TABLE `partidas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `numero_jugadores` int(11) NOT NULL CHECK (`numero_jugadores` BETWEEN 2 AND 5),
  `modo_juego` enum('digital','seguimiento') DEFAULT 'digital',
  `estado` enum('preparacion','en_curso','finalizada','pausada') DEFAULT 'preparacion',
  `ronda_actual` int(11) DEFAULT 1 CHECK (`ronda_actual` BETWEEN 1 AND 2),
  `turno_actual` int(11) DEFAULT 1 CHECK (`turno_actual` BETWEEN 1 AND 6),
  `jugador_activo` int(11) NULL,
  `dado_actual` int(11) NULL CHECK (`dado_actual` BETWEEN 1 AND 6),
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_inicio` datetime NULL,
  `fecha_finalizacion` datetime NULL,
  `creador_id` int(11) NULL,
  `ganador_id` int(11) NULL,
  `puntuacion_maxima` int(11) DEFAULT 0,
  `configuracion` json NULL,
  PRIMARY KEY (`id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha_creacion` (`fecha_creacion`),
  KEY `idx_creador` (`creador_id`),
  FOREIGN KEY (`creador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  FOREIGN KEY (`ganador_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabla de jugadores en partidas
CREATE TABLE `partida_jugadores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `partida_id` int(11) NOT NULL,
  `usuario_id` int(11) NULL,
  `nombre_jugador` varchar(30) NOT NULL,
  `orden_juego` int(11) NOT NULL CHECK (`orden_juego` BETWEEN 1 AND 5),
  `puntuacion_total` int(11) DEFAULT 0,
  `puntuacion_r1` int(11) DEFAULT 0,
  `puntuacion_r2` int(11) DEFAULT 0,
  `dinosaurios_totales` int(11) DEFAULT 0,
  `trex_bonus` int(11) DEFAULT 0,
  `estado` enum('activo','abandonado') DEFAULT 'activo',
  `tablero_estado` json NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_partida_orden` (`partida_id`, `orden_juego`),
  KEY `idx_partida` (`partida_id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_puntuacion` (`puntuacion_total`),
  FOREIGN KEY (`partida_id`) REFERENCES `partidas` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabla de dinosaurios (tipos)
CREATE TABLE `dinosaurios_tipos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(20) NOT NULL,
  `color` varchar(10) NOT NULL,
  `icono_perfil` varchar(50) NOT NULL,
  `icono_tablero` varchar(50) NOT NULL,
  `cantidad_total` int(11) DEFAULT 10,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`),
  UNIQUE KEY `color` (`color`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabla de recintos del tablero
CREATE TABLE `recintos_tipos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  `descripcion` text NOT NULL,
  `regla_colocacion` text NOT NULL,
  `regla_puntuacion` text NOT NULL,
  `capacidad_maxima` int(11) NULL,
  `tipo_zona` enum('izquierda','derecha','rio') NOT NULL,
  `coordenadas_css` json NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabla de dinosaurios colocados en partidas
CREATE TABLE `partida_dinosaurios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `partida_id` int(11) NOT NULL,
  `jugador_id` int(11) NOT NULL,
  `dinosaurio_tipo_id` int(11) NOT NULL,
  `recinto_id` int(11) NOT NULL,
  `posicion_en_recinto` int(11) NOT NULL,
  `ronda` int(11) NOT NULL CHECK (`ronda` BETWEEN 1 AND 2),
  `turno` int(11) NOT NULL CHECK (`turno` BETWEEN 1 AND 6),
  `puntos_generados` int(11) DEFAULT 0,
  `fecha_colocacion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_partida_jugador` (`partida_id`, `jugador_id`),
  KEY `idx_dinosaurio_tipo` (`dinosaurio_tipo_id`),
  KEY `idx_recinto` (`recinto_id`),
  KEY `idx_ronda_turno` (`ronda`, `turno`),
  FOREIGN KEY (`partida_id`) REFERENCES `partidas` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`jugador_id`) REFERENCES `partida_jugadores` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`dinosaurio_tipo_id`) REFERENCES `dinosaurios_tipos` (`id`),
  FOREIGN KEY (`recinto_id`) REFERENCES `recintos_tipos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabla de histórico de dados
CREATE TABLE `partida_dados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `partida_id` int(11) NOT NULL,
  `ronda` int(11) NOT NULL CHECK (`ronda` BETWEEN 1 AND 2),
  `turno` int(11) NOT NULL CHECK (`turno` BETWEEN 1 AND 6),
  `jugador_activo_id` int(11) NOT NULL,
  `cara_dado` int(11) NOT NULL CHECK (`cara_dado` BETWEEN 1 AND 6),
  `restriccion_aplicada` varchar(100) NOT NULL,
  `fecha_lanzamiento` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_partida_ronda_turno` (`partida_id`, `ronda`, `turno`),
  KEY `idx_partida` (`partida_id`),
  KEY `idx_jugador_activo` (`jugador_activo_id`),
  FOREIGN KEY (`partida_id`) REFERENCES `partidas` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`jugador_activo_id`) REFERENCES `partida_jugadores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabla de logs de acciones
CREATE TABLE `logs_partida` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `partida_id` int(11) NOT NULL,
  `jugador_id` int(11) NULL,
  `accion` varchar(50) NOT NULL,
  `descripcion` text NOT NULL,
  `datos_adicionales` json NULL,
  `fecha_accion` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_partida` (`partida_id`),
  KEY `idx_jugador` (`jugador_id`),
  KEY `idx_fecha` (`fecha_accion`),
  KEY `idx_accion` (`accion`),
  FOREIGN KEY (`partida_id`) REFERENCES `partidas` (`id`) ON DELETE CASCADE,
  FOREIGN KEY (`jugador_id`) REFERENCES `partida_jugadores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabla de estadísticas de usuarios
CREATE TABLE `usuario_estadisticas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `partidas_jugadas` int(11) DEFAULT 0,
  `partidas_ganadas` int(11) DEFAULT 0,
  `partidas_perdidas` int(11) DEFAULT 0,
  `puntuacion_maxima` int(11) DEFAULT 0,
  `puntuacion_promedio` decimal(5,2) DEFAULT 0.00,
  `tiempo_juego_total` int(11) DEFAULT 0, -- en minutos
  `racha_victorias_actual` int(11) DEFAULT 0,
  `racha_victorias_maxima` int(11) DEFAULT 0,
  `fecha_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario_id` (`usuario_id`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- Tabla de sesiones
CREATE TABLE `sesiones` (
  `id` varchar(128) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(500) NOT NULL,
  `datos_sesion` text NULL,
  `fecha_creacion` datetime DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `fecha_expiracion` datetime NOT NULL,
  `activa` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_expiracion` (`fecha_expiracion`),
  KEY `idx_activa` (`activa`),
  FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

-- DATOS INICIALES

-- Insertar tipos de dinosaurios
INSERT INTO `dinosaurios_tipos` (`nombre`, `color`, `icono_perfil`, `icono_tablero`, `cantidad_total`) VALUES
('T-Rex', 'verde', 'dino-1-perfil.png', 'dino-1-arriba.png', 10),
('Triceratops', 'azul', 'dino-2-perfil.png', 'dino-2-arriba.png', 10),
('Brontosaurus', 'amarillo', 'dino-3-perfil.png', 'dino-3-arriba.png', 10),
('Stegosaurus', 'rojo', 'dino-4-perfil.png', 'dino-4-arriba.png', 10),
('Pteranodon', 'naranja', 'dino-5-perfil.png', 'dino-5-arriba.png', 10),
('Parasaurolophus', 'rosa', 'dino-6-perfil.png', 'dino-6-arriba.png', 10);

-- Insertar tipos de recintos
INSERT INTO `recintos_tipos` (`nombre`, `descripcion`, `regla_colocacion`, `regla_puntuacion`, `capacidad_maxima`, `tipo_zona`) VALUES
('Bosque de la Semejanza', 'Solo puede albergar dinosaurios de la misma especie', 'Debe ocuparse de izquierda a derecha sin espacios intermedios. Solo dinosaurios de la misma especie.', 'Puntos según el número total de dinosaurios colocados en el recinto', 6, 'izquierda'),
('Prado de la Diferencia', 'Solo puede albergar dinosaurios de especies distintas', 'Debe ocuparse de izquierda a derecha sin espacios intermedios. Solo dinosaurios de especies diferentes.', 'Puntos según el número de dinosaurios colocados en el recinto', 6, 'izquierda'),
('Pradera del Amor', 'Puede albergar dinosaurios de todas las especies', 'Sin restricciones de colocación. Todas las especies permitidas.', '5 puntos por cada pareja de dinosaurios de la misma especie', 6, 'izquierda'),
('Trío Frondoso', 'Puede albergar hasta 3 dinosaurios', 'Máximo 3 dinosaurios sin importar la especie.', '7 puntos si hay exactamente 3 dinosaurios, 0 puntos en caso contrario', 3, 'derecha'),
('Rey de la Selva', 'Puede albergar solo 1 dinosaurio', 'Máximo 1 dinosaurio de cualquier especie.', '7 puntos si ningún jugador tiene más dinosaurios de esa especie en su parque', 1, 'derecha'),
('Isla Solitaria', 'Puede albergar solo 1 dinosaurio', 'Máximo 1 dinosaurio de cualquier especie.', '7 puntos si es el único de su especie en tu parque, 0 puntos en caso contrario', 1, 'derecha'),
('Río', 'Recinto especial para dinosaurios que no pueden colocarse en otros recintos', 'Sin restricciones. Capacidad ilimitada.', '1 punto por cada dinosaurio', NULL, 'rio');

-- Insertar usuario administrador por defecto
INSERT INTO `usuarios` (`username`, `email`, `password_hash`, `fecha_nacimiento`, `role`, `activo`) VALUES
('admin', 'admin@draftosaurus.local', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '1990-01-01', 'admin', 1);
-- Contraseña: password

-- Crear triggers para mantener estadísticas actualizadas

DELIMITER $

-- Trigger para actualizar estadísticas cuando se finaliza una partida
CREATE TRIGGER `actualizar_estadisticas_partida` 
AFTER UPDATE ON `partidas` 
FOR EACH ROW 
BEGIN
    IF NEW.estado = 'finalizada' AND OLD.estado != 'finalizada' THEN
        -- Actualizar estadísticas de todos los jugadores de la partida
        UPDATE usuario_estadisticas us
        INNER JOIN partida_jugadores pj ON us.usuario_id = pj.usuario_id
        SET 
            us.partidas_jugadas = us.partidas_jugadas + 1,
            us.partidas_ganadas = us.partidas_ganadas + IF(pj.id = (
                SELECT ganador_id FROM partidas WHERE id = NEW.id
            ), 1, 0),
            us.partidas_perdidas = us.partidas_perdidas + IF(pj.id != (
                SELECT ganador_id FROM partidas WHERE id = NEW.id
            ), 1, 0),
            us.puntuacion_maxima = GREATEST(us.puntuacion_maxima, pj.puntuacion_total),
            us.puntuacion_promedio = (
                SELECT AVG(puntuacion_total) 
                FROM partida_jugadores 
                WHERE usuario_id = us.usuario_id 
                AND partida_id IN (
                    SELECT id FROM partidas WHERE estado = 'finalizada'
                )
            )
        WHERE pj.partida_id = NEW.id AND pj.usuario_id IS NOT NULL;
    END IF;
END$

-- Trigger para crear estadísticas cuando se registra un nuevo usuario
CREATE TRIGGER `crear_estadisticas_usuario` 
AFTER INSERT ON `usuarios` 
FOR EACH ROW 
BEGIN
    INSERT INTO usuario_estadisticas (usuario_id) VALUES (NEW.id);
END$

DELIMITER ;

-- Índices adicionales para optimización
CREATE INDEX `idx_partidas_estado_fecha` ON `partidas` (`estado`, `fecha_creacion`);
CREATE INDEX `idx_jugadores_puntuacion_desc` ON `partida_jugadores` (`puntuacion_total` DESC);
CREATE INDEX `idx_dinosaurios_partida_recinto` ON `partida_dinosaurios` (`partida_id`, `recinto_id`);
CREATE INDEX `idx_logs_partida_fecha` ON `logs_partida` (`partida_id`, `fecha_accion`);

-- Crear vistas útiles

-- Vista de ranking de jugadores
CREATE VIEW `ranking_jugadores` AS
SELECT 
    u.id,
    u.username,
    u.foto_perfil,
    COALESCE(us.partidas_jugadas, 0) as partidas_jugadas,
    COALESCE(us.partidas_ganadas, 0) as partidas_ganadas,
    COALESCE(us.puntuacion_maxima, 0) as puntuacion_maxima,
    COALESCE(us.puntuacion_promedio, 0) as puntuacion_promedio,
    CASE 
        WHEN us.partidas_jugadas > 0 THEN ROUND((us.partidas_ganadas / us.partidas_jugadas) * 100, 2)
        ELSE 0 
    END as porcentaje_victorias
FROM usuarios u
LEFT JOIN usuario_estadisticas us ON u.id = us.usuario_id
WHERE u.activo = 1 AND u.role = 'player'
ORDER BY us.puntuacion_maxima DESC, us.partidas_ganadas DESC;

-- Vista de partidas recientes
CREATE VIEW `partidas_recientes` AS
SELECT 
    p.id,
    p.nombre,
    p.numero_jugadores,
    p.modo_juego,
    p.estado,
    p.fecha_creacion,
    p.fecha_finalizacion,
    u_creador.username as creador,
    u_ganador.username as ganador,
    p.puntuacion_maxima
FROM partidas p
LEFT JOIN usuarios u_creador ON p.creador_id = u_creador.id
LEFT JOIN partida_jugadores pj_ganador ON p.ganador_id = pj_ganador.id
LEFT JOIN usuarios u_ganador ON pj_ganador.usuario_id = u_ganador.id
ORDER BY p.fecha_creacion DESC;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;