<?php
/**
 * API de Gestión de Perfil de Usuario
 * /api/user/profile.php
 */

require_once '../config/config.php';

// Verificar sesión
checkUserPermission('player');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetProfile();
            break;
        case 'PUT':
            handleUpdateProfile();
            break;
        case 'POST':
            handleUploadPhoto();
            break;
        default:
            jsonResponse(null, 'Método no permitido', false, 405);
    }
    
} catch (Exception $e) {
    logError('Error en gestión de perfil: ' . $e->getMessage(), [
        'method' => $_SERVER['REQUEST_METHOD'],
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}

function handleGetProfile() {
    $user = new User();
    if (!$user->loadById($_SESSION['user_id'])) {
        jsonResponse(null, 'Usuario no encontrado', false, 404);
    }
    
    // Obtener estadísticas
    $stats = $user->getStats();
    $ranking = $user->getRanking();
    
    jsonResponse([
        'user' => $user->toArray(true),
        'stats' => $stats,
        'ranking' => $ranking
    ], 'Perfil obtenido correctamente', true);
}

function handleUpdateProfile() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(null, 'Datos de entrada no válidos', false, 400);
    }
    
    $user = new User();
    if (!$user->loadById($_SESSION['user_id'])) {
        jsonResponse(null, 'Usuario no encontrado', false, 404);
    }
    
    $updated = false;
    
    // Actualizar username si se proporciona
    if (isset($input['username']) && !empty($input['username'])) {
        $newUsername = sanitizeInput($input['username']);
        if ($newUsername !== $user->getUsername()) {
            if ($user->existsByUsername($newUsername)) {
                jsonResponse(null, 'El nombre de usuario ya está en uso', false, 409);
            }
            $user->setUsername($newUsername);
            $_SESSION['username'] = $newUsername;
            $updated = true;
        }
    }
    
    // Actualizar email si se proporciona
    if (isset($input['email']) && !empty($input['email'])) {
        $newEmail = sanitizeInput($input['email']);
        if ($newEmail !== $user->getEmail()) {
            if ($user->existsByEmail($newEmail)) {
                jsonResponse(null, 'El email ya está registrado', false, 409);
            }
            $user->setEmail($newEmail);
            $updated = true;
        }
    }
    
    // Actualizar fecha de nacimiento si se proporciona
    if (isset($input['birthdate']) && !empty($input['birthdate'])) {
        $user->setFechaNacimiento($input['birthdate']);
        $updated = true;
    }
    
    if ($updated) {
        if ($user->update()) {
            logError("Perfil actualizado: {$user->getUsername()}", [
                'user_id' => $_SESSION['user_id'],
                'changes' => array_keys($input)
            ]);
            
            jsonResponse($user->toArray(true), 'Perfil actualizado correctamente', true);
        } else {
            jsonResponse(null, 'Error al actualizar perfil', false, 500);
        }
    } else {
        jsonResponse($user->toArray(true), 'No hay cambios que guardar', true);
    }
}

function handleUploadPhoto() {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        jsonResponse(null, 'No se recibió ningún archivo válido', false, 400);
    }
    
    $file = $_FILES['photo'];
    
    // Validar tipo de archivo
    if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
        jsonResponse(null, 'Tipo de archivo no permitido. Solo se permiten JPG, PNG y GIF', false, 400);
    }
    
    // Validar tamaño
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        $maxSizeMB = UPLOAD_MAX_SIZE / 1024 / 1024;
        jsonResponse(null, "El archivo es demasiado grande. Máximo {$maxSizeMB}MB", false, 400);
    }
    
    // Generar nombre único
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $extension;
    $uploadPath = UPLOAD_PATH . 'profiles/' . $fileName;
    
    // Crear directorio si no existe
    if (!is_dir(UPLOAD_PATH . 'profiles/')) {
        mkdir(UPLOAD_PATH . 'profiles/', 0755, true);
    }
    
    // Mover archivo
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        // Actualizar base de datos
        $user = new User();
        if ($user->loadById($_SESSION['user_id'])) {
            // Eliminar foto anterior si existe
            $oldPhoto = $user->getFotoPerfil();
            if ($oldPhoto && strpos($oldPhoto, 'profile_') === 0) {
                $oldPath = UPLOAD_PATH . 'profiles/' . $oldPhoto;
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }
            
            $user->setFotoPerfil($fileName);
            if ($user->update()) {
                jsonResponse([
                    'filename' => $fileName,
                    'url' => 'uploads/profiles/' . $fileName
                ], 'Foto de perfil actualizada', true);
            } else {
                // Si falla la BD, eliminar archivo subido
                unlink($uploadPath);
                jsonResponse(null, 'Error al guardar en base de datos', false, 500);
            }
        } else {
            unlink($uploadPath);
            jsonResponse(null, 'Usuario no encontrado', false, 404);
        }
    } else {
        jsonResponse(null, 'Error al subir el archivo', false, 500);
    }
}
?>

<?php
/**
 * API de Cambio de Contraseña
 * /api/user/password.php
 */

require_once '../config/config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

// Verificar sesión
checkUserPermission('player');

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(null, 'Datos de entrada no válidos', false, 400);
    }
    
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    // Validaciones
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        jsonResponse(null, 'Todos los campos son requeridos', false, 400);
    }
    
    if ($newPassword !== $confirmPassword) {
        jsonResponse(null, 'Las contraseñas nuevas no coinciden', false, 400);
    }
    
    if (strlen($newPassword) < MIN_PASSWORD_LENGTH) {
        jsonResponse(null, 'La nueva contraseña debe tener al menos ' . MIN_PASSWORD_LENGTH . ' caracteres', false, 400);
    }
    
    // Validar fortaleza de contraseña
    $validation = validatePassword($newPassword);
    if (!$validation['valid']) {
        jsonResponse(null, implode(', ', $validation['errors']), false, 400);
    }
    
    $user = new User();
    if (!$user->loadById($_SESSION['user_id'])) {
        jsonResponse(null, 'Usuario no encontrado', false, 404);
    }
    
    // Cambiar contraseña
    if ($user->changePassword($currentPassword, $newPassword)) {
        logError("Contraseña cambiada: {$user->getUsername()}", [
            'user_id' => $_SESSION['user_id']
        ]);
        
        jsonResponse(null, 'Contraseña actualizada correctamente', true);
    } else {
        jsonResponse(null, 'Contraseña actual incorrecta', false, 400);
    }
    
} catch (Exception $e) {
    logError('Error cambiando contraseña: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}
?>

<?php
/**
 * Utilidad para generar fotos de perfil aleatorias
 * /api/utils/random-avatar.php
 */

require_once '../config/config.php';

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

try {
    $avatars = [
        'foto_usuario-1.png',
        'foto_usuario-2.png',
        'foto_usuario-3.png',
        'foto_usuario-4.png',
        'foto_usuario-5.png'
    ];
    
    $randomAvatar = $avatars[array_rand($avatars)];
    
    jsonResponse([
        'filename' => $randomAvatar,
        'url' => 'img/iconos/' . $randomAvatar,
        'all_avatars' => array_map(function($avatar) {
            return [
                'filename' => $avatar,
                'url' => 'img/iconos/' . $avatar
            ];
        }, $avatars)
    ], 'Avatar aleatorio generado', true);
    
} catch (Exception $e) {
    logError('Error generando avatar: ' . $e->getMessage());
    jsonResponse(null, 'Error interno del servidor', false, 500);
}
?>

<?php
/**
 * API de Estadísticas de Usuario
 * /api/user/stats.php
 */

require_once '../config/config.php';

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

// Verificar sesión
checkUserPermission('player');

try {
    $user = new User();
    if (!$user->loadById($_SESSION['user_id'])) {
        jsonResponse(null, 'Usuario no encontrado', false, 404);
    }
    
    $db = Database::getInstance();
    
    // Estadísticas básicas
    $stats = $user->getStats();
    $ranking = $user->getRanking();
    
    // Historial de partidas recientes
    $recentGamesQuery = "
        SELECT 
            p.id,
            p.nombre,
            p.fecha_finalizacion,
            pj.puntuacion_total,
            p.ganador_id,
            (pj.id = p.ganador_id) as ganador
        FROM partidas p
        INNER JOIN partida_jugadores pj ON p.id = pj.partida_id
        WHERE pj.usuario_id = ? AND p.estado = 'finalizada'
        ORDER BY p.fecha_finalizacion DESC
        LIMIT 10
    ";
    
    $recentGames = $db->query($recentGamesQuery, [$_SESSION['user_id']]);
    
    // Estadísticas por recinto
    $recintoStatsQuery = "
        SELECT 
            rt.nombre,
            COUNT(pd.id) as dinosaurios_colocados,
            AVG(pd.puntos_generados) as puntos_promedio
        FROM partida_dinosaurios pd
        INNER JOIN partida_jugadores pj ON pd.jugador_id = pj.id
        INNER JOIN recintos_tipos rt ON pd.recinto_id = rt.id
        WHERE pj.usuario_id = ?
        GROUP BY rt.id, rt.nombre
        ORDER BY dinosaurios_colocados DESC
    ";
    
    $recintoStats = $db->query($recintoStatsQuery, [$_SESSION['user_id']]);
    
    // Dinosaurios favoritos
    $dinoStatsQuery = "
        SELECT 
            dt.nombre,
            dt.color,
            COUNT(pd.id) as veces_usado
        FROM partida_dinosaurios pd
        INNER JOIN partida_jugadores pj ON pd.jugador_id = pj.id
        INNER JOIN dinosaurios_tipos dt ON pd.dinosaurio_tipo_id = dt.id
        WHERE pj.usuario_id = ?
        GROUP BY dt.id, dt.nombre, dt.color
        ORDER BY veces_usado DESC
        LIMIT 6
    ";
    
    $dinosaurStats = $db->query($dinoStatsQuery, [$_SESSION['user_id']]);
    
    // Progresión de puntuaciones (últimos 10 juegos)
    $progressQuery = "
        SELECT 
            p.fecha_finalizacion,
            pj.puntuacion_total
        FROM partidas p
        INNER JOIN partida_jugadores pj ON p.id = pj.partida_id
        WHERE pj.usuario_id = ? AND p.estado = 'finalizada'
        ORDER BY p.fecha_finalizacion DESC
        LIMIT 10
    ";
    
    $progressData = array_reverse($db->query($progressQuery, [$_SESSION['user_id']]));
    
    // Comparación con promedio global
    $globalAvgQuery = "
        SELECT 
            AVG(puntuacion_total) as promedio_global,
            MAX(puntuacion_total) as record_global
        FROM partida_jugadores pj
        INNER JOIN partidas p ON pj.partida_id = p.id
        WHERE p.estado = 'finalizada' AND pj.usuario_id IS NOT NULL
    ";
    
    $globalStats = $db->query($globalAvgQuery)[0];
    
    jsonResponse([
        'basic_stats' => $stats,
        'ranking' => $ranking,
        'recent_games' => $recentGames,
        'enclosure_stats' => $recintoStats,
        'dinosaur_stats' => $dinosaurStats,
        'score_progression' => $progressData,
        'global_comparison' => [
            'user_average' => $stats['puntuacion_promedio'],
            'global_average' => round($globalStats['promedio_global'], 2),
            'user_record' => $stats['puntuacion_maxima'],
            'global_record' => $globalStats['record_global']
        ]
    ], 'Estadísticas obtenidas correctamente', true);
    
} catch (Exception $e) {
    logError('Error obteniendo estadísticas: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}
?>