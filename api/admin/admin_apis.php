<?php
/**
 * API de Estadísticas para Administrador
 * /api/admin/stats.php
 */

require_once '../config/config.php';

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

// Verificar sesión y permisos de administrador
checkUserPermission('admin');

try {
    $db = Database::getInstance();
    $stats = [];
    
    // Estadísticas de usuarios
    $stats['totalUsers'] = $db->count('usuarios', 'activo = 1');
    $stats['newUsersToday'] = $db->count('usuarios', 'DATE(fecha_registro) = CURDATE()');
    $stats['activeToday'] = $db->count('usuarios', 'DATE(ultimo_acceso) = CURDATE()');
    $stats['adminUsers'] = $db->count('usuarios', 'role = ? AND activo = 1', ['admin']);
    
    // Estadísticas de partidas
    $stats['totalGames'] = $db->count('partidas');
    $stats['activeGames'] = $db->count('partidas', 'estado IN (?, ?)', ['preparacion', 'en_curso']);
    $stats['finishedGames'] = $db->count('partidas', 'estado = ?', ['finalizada']);
    $stats['gamesToday'] = $db->count('partidas', 'DATE(fecha_creacion) = CURDATE()');
    
    // Partidas por modo
    $stats['digitalGames'] = $db->count('partidas', 'modo_juego = ?', ['digital']);
    $stats['trackingGames'] = $db->count('partidas', 'modo_juego = ?', ['seguimiento']);
    
    // Estadísticas de tiempo promedio de partida
    $avgDurationQuery = "
        SELECT AVG(TIMESTAMPDIFF(MINUTE, fecha_inicio, fecha_finalizacion)) as avg_duration
        FROM partidas 
        WHERE estado = 'finalizada' 
        AND fecha_inicio IS NOT NULL 
        AND fecha_finalizacion IS NOT NULL
    ";
    $avgDuration = $db->query($avgDurationQuery);
    $stats['avgGameDuration'] = round($avgDuration[0]['avg_duration'] ?? 0, 1);
    
    // Top 5 jugadores por puntuación
    $topPlayersQuery = "
        SELECT u.username, us.puntuacion_maxima, us.partidas_ganadas
        FROM usuarios u
        INNER JOIN usuario_estadisticas us ON u.id = us.usuario_id
        WHERE u.activo = 1 AND u.role = 'player'
        ORDER BY us.puntuacion_maxima DESC, us.partidas_ganadas DESC
        LIMIT 5
    ";
    $stats['topPlayers'] = $db->query($topPlayersQuery);
    
    // Estadísticas de movimientos
    $stats['totalMoves'] = $db->count('partida_dinosaurios');
    $stats['movesToday'] = $db->count('partida_dinosaurios', 'DATE(fecha_colocacion) = CURDATE()');
    
    // Distribución por número de jugadores
    $playerDistQuery = "
        SELECT numero_jugadores, COUNT(*) as count
        FROM partidas
        GROUP BY numero_jugadores
        ORDER BY numero_jugadores
    ";
    $stats['playerDistribution'] = $db->query($playerDistQuery);
    
    // Estadísticas de dinosaurios más utilizados
    $dinoStatsQuery = "
        SELECT dt.nombre, COUNT(*) as usage_count
        FROM partida_dinosaurios pd
        INNER JOIN dinosaurios_tipos dt ON pd.dinosaurio_tipo_id = dt.id
        GROUP BY dt.id, dt.nombre
        ORDER BY usage_count DESC
        LIMIT 6
    ";
    $stats['dinosaurUsage'] = $db->query($dinoStatsQuery);
    
    // Recintos más populares
    $recintoStatsQuery = "
        SELECT rt.nombre, COUNT(*) as usage_count
        FROM partida_dinosaurios pd
        INNER JOIN recintos_tipos rt ON pd.recinto_id = rt.id
        WHERE rt.id != 7  -- Excluir río
        GROUP BY rt.id, rt.nombre
        ORDER BY usage_count DESC
    ";
    $stats['popularEnclosures'] = $db->query($recintoStatsQuery);
    
    // Estadísticas de registro por mes (últimos 6 meses)
    $registrationStatsQuery = "
        SELECT 
            DATE_FORMAT(fecha_registro, '%Y-%m') as month,
            COUNT(*) as registrations
        FROM usuarios
        WHERE fecha_registro >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(fecha_registro, '%Y-%m')
        ORDER BY month DESC
    ";
    $stats['monthlyRegistrations'] = $db->query($registrationStatsQuery);
    
    // Estadísticas de partidas por día (última semana)
    $dailyGamesQuery = "
        SELECT 
            DATE(fecha_creacion) as date,
            COUNT(*) as games
        FROM partidas
        WHERE fecha_creacion >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(fecha_creacion)
        ORDER BY date DESC
    ";
    $stats['dailyGames'] = $db->query($dailyGamesQuery);
    
    // Información del sistema
    $stats['systemInfo'] = [
        'php_version' => PHP_VERSION,
        'app_version' => APP_VERSION,
        'environment' => APP_ENV,
        'database_size' => $db->selectOne("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
            FROM information_schema.tables 
            WHERE table_schema = ?
        ", [DB_NAME])['size_mb'] ?? 0
    ];
    
    jsonResponse($stats, 'Estadísticas obtenidas correctamente', true);
    
} catch (Exception $e) {
    logError('Error obteniendo estadísticas de admin: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}
?>

<?php
/**
 * API de Gestión de Usuarios para Administrador
 * /api/admin/users.php
 */

require_once '../config/config.php';

// Verificar sesión y permisos de administrador
checkUserPermission('admin');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetUsers();
            break;
        case 'PUT':
            handleUpdateUser();
            break;
        case 'DELETE':
            handleDeleteUser();
            break;
        default:
            jsonResponse(null, 'Método no permitido', false, 405);
    }
    
} catch (Exception $e) {
    logError('Error en gestión de usuarios: ' . $e->getMessage(), [
        'method' => $_SERVER['REQUEST_METHOD'],
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}

function handleGetUsers() {
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 20), 100); // Máximo 100 por página
    $search = sanitizeInput($_GET['search'] ?? '');
    $role = sanitizeInput($_GET['role'] ?? '');
    $status = sanitizeInput($_GET['status'] ?? '');
    
    $filters = [];
    if ($search) $filters['search'] = $search;
    if ($role) $filters['role'] = $role;
    if ($status) $filters['status'] = $status;
    
    $result = User::getAll($page, $limit, $filters);
    
    // Agregar estadísticas de cada usuario
    $db = Database::getInstance();
    foreach ($result['users'] as &$user) {
        $stats = $db->selectOne('usuario_estadisticas', '*', 'usuario_id = ?', [$user['id']]);
        $user['stats'] = $stats ?: [
            'partidas_jugadas' => 0,
            'partidas_ganadas' => 0,
            'puntuacion_maxima' => 0
        ];
        
        // Última actividad
        $user['last_activity'] = $user['ultimo_acceso'] ? 
            formatDate($user['ultimo_acceso']) : 'Nunca';
    }
    
    jsonResponse($result, 'Usuarios obtenidos correctamente', true);
}

function handleUpdateUser() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['id'])) {
        jsonResponse(null, 'Datos incompletos', false, 400);
    }
    
    $userId = (int)$input['id'];
    $newRole = sanitizeInput($input['role'] ?? '');
    $active = isset($input['active']) ? (bool)$input['active'] : null;
    
    // No permitir que el admin se quite a sí mismo los permisos
    if ($userId === $_SESSION['user_id'] && $newRole === 'player') {
        jsonResponse(null, 'No puedes quitarte los permisos de administrador', false, 400);
    }
    
    $user = new User();
    if (!$user->loadById($userId)) {
        jsonResponse(null, 'Usuario no encontrado', false, 404);
    }
    
    // Actualizar campos
    if ($newRole && in_array($newRole, ['admin', 'player'])) {
        $user->setRole($newRole);
    }
    
    if ($active !== null) {
        $user->setActivo($active);
    }
    
    if ($user->update()) {
        logError("Usuario actualizado por admin: {$user->getUsername()}", [
            'admin_id' => $_SESSION['user_id'],
            'target_user_id' => $userId,
            'changes' => $input
        ]);
        
        jsonResponse([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'role' => $user->getRole(),
            'active' => $user->isActivo()
        ], 'Usuario actualizado correctamente', true);
    } else {
        jsonResponse(null, 'Error al actualizar usuario', false, 500);
    }
}

function handleDeleteUser() {
    $userId = (int)($_GET['id'] ?? 0);
    
    if (!$userId) {
        jsonResponse(null, 'ID de usuario requerido', false, 400);
    }
    
    // No permitir que el admin se elimine a sí mismo
    if ($userId === $_SESSION['user_id']) {
        jsonResponse(null, 'No puedes eliminarte a ti mismo', false, 400);
    }
    
    $user = new User();
    if (!$user->loadById($userId)) {
        jsonResponse(null, 'Usuario no encontrado', false, 404);
    }
    
    if ($user->delete()) {
        logError("Usuario eliminado por admin: {$user->getUsername()}", [
            'admin_id' => $_SESSION['user_id'],
            'target_user_id' => $userId
        ]);
        
        jsonResponse(null, 'Usuario eliminado correctamente', true);
    } else {
        jsonResponse(null, 'Error al eliminar usuario', false, 500);
    }
}
?>

<?php
/**
 * API de Gestión de Partidas para Administrador
 * /api/admin/games.php
 */

require_once '../config/config.php';

// Verificar sesión y permisos de administrador
checkUserPermission('admin');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    
    switch ($method) {
        case 'GET':
            handleGetGames();
            break;
        case 'DELETE':
            handleDeleteGame();
            break;
        default:
            jsonResponse(null, 'Método no permitido', false, 405);
    }
    
} catch (Exception $e) {
    logError('Error en gestión de partidas: ' . $e->getMessage(), [
        'method' => $_SERVER['REQUEST_METHOD'],
        'user_id' => $_SESSION['user_id'] ?? 'unknown'
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}

function handleGetGames() {
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 20), 100);
    $status = sanitizeInput($_GET['status'] ?? '');
    $mode = sanitizeInput($_GET['mode'] ?? '');
    $dateFrom = sanitizeInput($_GET['date_from'] ?? '');
    $dateTo = sanitizeInput($_GET['date_to'] ?? '');
    
    $db = Database::getInstance();
    $offset = ($page - 1) * $limit;
    
    // Construir WHERE clause
    $where = [];
    $params = [];
    
    if ($status) {
        $where[] = 'p.estado = ?';
        $params[] = $status;
    }
    
    if ($mode) {
        $where[] = 'p.modo_juego = ?';
        $params[] = $mode;
    }
    
    if ($dateFrom) {
        $where[] = 'DATE(p.fecha_creacion) >= ?';
        $params[] = $dateFrom;
    }
    
    if ($dateTo) {
        $where[] = 'DATE(p.fecha_creacion) <= ?';
        $params[] = $dateTo;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Obtener total
    $totalQuery = "SELECT COUNT(*) as total FROM partidas p $whereClause";
    $total = $db->query($totalQuery, $params)[0]['total'];
    
    // Obtener partidas
    $gamesQuery = "
        SELECT 
            p.*,
            u_creador.username as creador_username,
            u_ganador.username as ganador_username,
            COUNT(pj.id) as jugadores_actuales
        FROM partidas p
        LEFT JOIN usuarios u_creador ON p.creador_id = u_creador.id
        LEFT JOIN partida_jugadores pg ON p.ganador_id = pg.id
        LEFT JOIN usuarios u_ganador ON pg.usuario_id = u_ganador.id
        LEFT JOIN partida_jugadores pj ON p.id = pj.partida_id
        $whereClause
        GROUP BY p.id
        ORDER BY p.fecha_creacion DESC
        LIMIT $limit OFFSET $offset
    ";
    
    $games = $db->query($gamesQuery, $params);
    
    // Formatear datos
    foreach ($games as &$game) {
        $game['fecha_creacion_formatted'] = formatDate($game['fecha_creacion']);
        $game['fecha_inicio_formatted'] = $game['fecha_inicio'] ? formatDate($game['fecha_inicio']) : null;
        $game['fecha_finalizacion_formatted'] = $game['fecha_finalizacion'] ? formatDate($game['fecha_finalizacion']) : null;
        
        // Calcular duración si está finalizada
        if ($game['fecha_inicio'] && $game['fecha_finalizacion']) {
            $inicio = new DateTime($game['fecha_inicio']);
            $fin = new DateTime($game['fecha_finalizacion']);
            $duracion = $inicio->diff($fin);
            $game['duracion_minutos'] = ($duracion->h * 60) + $duracion->i;
        }
    }
    
    $result = [
        'games' => $games,
        'total' => (int)$total,
        'page' => $page,
        'limit' => $limit,
        'pages' => ceil($total / $limit)
    ];
    
    jsonResponse($result, 'Partidas obtenidas correctamente', true);
}

function handleDeleteGame() {
    $gameId = (int)($_GET['id'] ?? 0);
    
    if (!$gameId) {
        jsonResponse(null, 'ID de partida requerido', false, 400);
    }
    
    $db = Database::getInstance();
    
    // Verificar que la partida existe
    $game = $db->selectOne('partidas', '*', 'id = ?', [$gameId]);
    if (!$game) {
        jsonResponse(null, 'Partida no encontrada', false, 404);
    }
    
    try {
        $db->beginTransaction();
        
        // Eliminar en orden para respetar foreign keys
        $db->delete('logs_partida', 'partida_id = ?', [$gameId]);
        $db->delete('partida_dados', 'partida_id = ?', [$gameId]);
        $db->delete('partida_dinosaurios', 'partida_id = ?', [$gameId]);
        $db->delete('partida_jugadores', 'partida_id = ?', [$gameId]);
        $db->delete('partidas', 'id = ?', [$gameId]);
        
        $db->commit();
        
        logError("Partida eliminada por admin: {$game['nombre']}", [
            'admin_id' => $_SESSION['user_id'],
            'game_id' => $gameId
        ]);
        
        jsonResponse(null, 'Partida eliminada correctamente', true);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}
?>