<?php
/**
 * API para crear nueva partida
 * /api/game/create.php
 */

require_once '../config/config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

// Verificar sesión
session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(null, 'Sesión requerida', false, 401);
}

try {
    // Obtener datos del cuerpo de la petición
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(null, 'Datos de entrada no válidos', false, 400);
    }
    
    // Validar y sanitizar campos
    $gameName = sanitizeInput($input['gameName'] ?? '');
    $numPlayers = (int)($input['numPlayers'] ?? 0);
    $mode = sanitizeInput($input['mode'] ?? 'digital');
    
    // Validaciones
    $errors = [];
    
    if (empty($gameName)) {
        $errors[] = 'El nombre de la partida es requerido';
    } elseif (strlen($gameName) > MAX_GAME_NAME_LENGTH) {
        $errors[] = 'El nombre de la partida es demasiado largo';
    }
    
    if ($numPlayers < MIN_PLAYERS || $numPlayers > MAX_PLAYERS) {
        $errors[] = 'Número de jugadores debe estar entre ' . MIN_PLAYERS . ' y ' . MAX_PLAYERS;
    }
    
    if (!in_array($mode, ['digital', 'seguimiento'])) {
        $errors[] = 'Modo de juego no válido';
    }
    
    if (!empty($errors)) {
        jsonResponse(['errors' => $errors], implode(', ', $errors), false, 400);
    }
    
    // Crear partida en base de datos
    $db = Database::getInstance();
    
    $gameData = [
        'nombre' => $gameName,
        'numero_jugadores' => $numPlayers,
        'modo_juego' => $mode,
        'estado' => 'preparacion',
        'creador_id' => $_SESSION['user_id'],
        'fecha_creacion' => date('Y-m-d H:i:s'),
        'configuracion' => json_encode([
            'ronda_actual' => 1,
            'turno_actual' => 1,
            'jugador_activo' => 0
        ])
    ];
    
    $gameId = $db->insert('partidas', $gameData);
    
    // Crear jugador principal (creador)
    $playerData = [
        'partida_id' => $gameId,
        'usuario_id' => $_SESSION['user_id'],
        'nombre_jugador' => $_SESSION['username'],
        'orden_juego' => 1,
        'tablero_estado' => json_encode([
            1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => []
        ])
    ];
    
    $playerId = $db->insert('partida_jugadores', $playerData);
    
    // Crear jugadores IA o locales según el modo
    for ($i = 2; $i <= $numPlayers; $i++) {
        $aiPlayerData = [
            'partida_id' => $gameId,
            'usuario_id' => null,
            'nombre_jugador' => $mode === 'digital' ? "IA Jugador $i" : "Jugador $i",
            'orden_juego' => $i,
            'tablero_estado' => json_encode([
                1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => []
            ])
        ];
        
        $db->insert('partida_jugadores', $aiPlayerData);
    }
    
    // Log de creación
    $logData = [
        'partida_id' => $gameId,
        'jugador_id' => $playerId,
        'accion' => 'crear_partida',
        'descripcion' => "Partida '$gameName' creada en modo $mode con $numPlayers jugadores",
        'datos_adicionales' => json_encode($gameData)
    ];
    
    $db->insert('logs_partida', $logData);
    
    // Respuesta exitosa
    jsonResponse([
        'id' => $gameId,
        'nombre' => $gameName,
        'numPlayers' => $numPlayers,
        'mode' => $mode,
        'estado' => 'preparacion',
        'creador' => $_SESSION['username'],
        'playerId' => $playerId
    ], 'Partida creada exitosamente', true, 201);
    
} catch (Exception $e) {
    logError('Error creando partida: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'input' => $input ?? [],
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}
?>

<?php
/**
 * API para realizar movimiento en partida
 * /api/game/move.php
 */

require_once '../config/config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

// Verificar sesión
session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(null, 'Sesión requerida', false, 401);
}

try {
    // Obtener datos del movimiento
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(null, 'Datos de entrada no válidos', false, 400);
    }
    
    $gameId = (int)($input['gameId'] ?? 0);
    $playerId = (int)($input['playerId'] ?? 0);
    $dinosaurType = (int)($input['dinosaurType'] ?? 0);
    $recintoId = (int)($input['recintoId'] ?? 0);
    $round = (int)($input['round'] ?? 1);
    $turn = (int)($input['turn'] ?? 1);
    
    // Validaciones básicas
    if (!$gameId || !$playerId || !$dinosaurType || !$recintoId) {
        jsonResponse(null, 'Datos de movimiento incompletos', false, 400);
    }
    
    $db = Database::getInstance();
    
    // Verificar que la partida existe y está activa
    $game = $db->selectOne('partidas', '*', 'id = ? AND estado IN (?, ?)', 
                          ['id' => $gameId, 'preparacion', 'en_curso']);
    
    if (!$game) {
        jsonResponse(null, 'Partida no encontrada o no activa', false, 404);
    }
    
    // Verificar que el jugador pertenece a la partida
    $player = $db->selectOne('partida_jugadores', '*', 
                            'id = ? AND partida_id = ?', 
                            ['id' => $playerId, 'partida_id' => $gameId]);
    
    if (!$player) {
        jsonResponse(null, 'Jugador no encontrado en esta partida', false, 403);
    }
    
    // Verificar que es el turno del jugador (solo en modo digital)
    if ($game['modo_juego'] === 'digital') {
        $config = json_decode($game['configuracion'], true);
        $expectedPlayer = $config['jugador_activo'] ?? 0;
        
        if ($player['orden_juego'] - 1 !== $expectedPlayer) {
            jsonResponse(null, 'No es tu turno', false, 403);
        }
    }
    
    // Validar reglas del juego
    $isValidMove = validateGameMove($gameId, $playerId, $dinosaurType, $recintoId, $db);
    
    if (!$isValidMove['valid']) {
        jsonResponse(null, $isValidMove['message'], false, 400);
    }
    
    // Si no se puede colocar en el recinto solicitado, mover al río
    $finalRecintoId = $isValidMove['forcedToRiver'] ? 7 : $recintoId;
    
    // Registrar el movimiento
    $moveData = [
        'partida_id' => $gameId,
        'jugador_id' => $playerId,
        'dinosaurio_tipo_id' => $dinosaurType,
        'recinto_id' => $finalRecintoId,
        'posicion_en_recinto' => $isValidMove['position'],
        'ronda' => $round,
        'turno' => $turn,
        'puntos_generados' => 0 // Se calculará al final
    ];
    
    $moveId = $db->insert('partida_dinosaurios', $moveData);
    
    // Actualizar tablero del jugador
    $tablero = json_decode($player['tablero_estado'], true);
    $tablero[$finalRecintoId][] = [
        'tipo' => $dinosaurType,
        'id' => $moveId,
        'ronda' => $round,
        'turno' => $turn
    ];
    
    $db->update('partida_jugadores', 
                ['tablero_estado' => json_encode($tablero)],
                'id = ?', ['id' => $playerId]);
    
    // Log del movimiento
    $logData = [
        'partida_id' => $gameId,
        'jugador_id' => $playerId,
        'accion' => 'colocar_dinosaurio',
        'descripcion' => "Dinosaurio tipo $dinosaurType colocado en recinto $finalRecintoId",
        'datos_adicionales' => json_encode($moveData)
    ];
    
    $db->insert('logs_partida', $logData);
    
    // Respuesta exitosa
    jsonResponse([
        'moveId' => $moveId,
        'finalRecinto' => $finalRecintoId,
        'position' => $isValidMove['position'],
        'forcedToRiver' => $isValidMove['forcedToRiver'] ?? false,
        'tablero' => $tablero
    ], 'Movimiento realizado exitosamente', true, 200);
    
} catch (Exception $e) {
    logError('Error realizando movimiento: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'game_id' => $gameId ?? 'unknown',
        'input' => $input ?? []
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}

/**
 * Validar reglas del juego para un movimiento
 */
function validateGameMove($gameId, $playerId, $dinosaurType, $recintoId, $db) {
    // Obtener estado actual del tablero
    $player = $db->selectOne('partida_jugadores', 'tablero_estado', 'id = ?', ['id' => $playerId]);
    $tablero = json_decode($player['tablero_estado'], true);
    
    // Obtener información del recinto
    $recinto = $db->selectOne('recintos_tipos', '*', 'id = ?', ['id' => $recintoId]);
    if (!$recinto) {
        return ['valid' => false, 'message' => 'Recinto no válido'];
    }
    
    $dinosauriosEnRecinto = $tablero[$recintoId] ?? [];
    
    // Verificar capacidad
    if ($recinto['capacidad_maxima'] && count($dinosauriosEnRecinto) >= $recinto['capacidad_maxima']) {
        // Si no cabe, se debe mover al río
        return [
            'valid' => true, 
            'forcedToRiver' => true,
            'position' => count($tablero[7] ?? []) + 1,
            'message' => 'Movido al río por falta de espacio'
        ];
    }
    
    // Validar reglas específicas del recinto
    switch ($recinto['id']) {
        case 1: // Bosque de la Semejanza - misma especie
            if (!empty($dinosauriosEnRecinto)) {
                $primerTipo = $dinosauriosEnRecinto[0]['tipo'];
                if ($primerTipo !== $dinosaurType) {
                    return [
                        'valid' => true,
                        'forcedToRiver' => true,
                        'position' => count($tablero[7] ?? []) + 1,
                        'message' => 'Movido al río: recinto solo acepta misma especie'
                    ];
                }
            }
            break;
            
        case 2: // Prado de la Diferencia - especies distintas
            foreach ($dinosauriosEnRecinto as $dino) {
                if ($dino['tipo'] === $dinosaurType) {
                    return [
                        'valid' => true,
                        'forcedToRiver' => true,
                        'position' => count($tablero[7] ?? []) + 1,
                        'message' => 'Movido al río: recinto solo acepta especies distintas'
                    ];
                }
            }
            break;
            
        case 3: // Pradera del Amor - sin restricciones especiales
            break;
            
        case 4: // Trío Frondoso - máximo 3
            if (count($dinosauriosEnRecinto) >= 3) {
                return [
                    'valid' => true,
                    'forcedToRiver' => true,
                    'position' => count($tablero[7] ?? []) + 1,
                    'message' => 'Movido al río: Trío Frondoso ya completo'
                ];
            }
            break;
            
        case 5: // Rey de la Selva - máximo 1
        case 6: // Isla Solitaria - máximo 1
            if (count($dinosauriosEnRecinto) >= 1) {
                return [
                    'valid' => true,
                    'forcedToRiver' => true,
                    'position' => count($tablero[7] ?? []) + 1,
                    'message' => 'Movido al río: recinto ya ocupado'
                ];
            }
            break;
            
        case 7: // Río - siempre válido
            break;
    }
    
    return [
        'valid' => true,
        'position' => count($dinosauriosEnRecinto) + 1,
        'forcedToRiver' => false
    ];
}
?>

<?php
/**
 * API para obtener estado de partida
 * /api/game/status.php
 */

require_once '../config/config.php';

// Solo permitir GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

try {
    $gameId = (int)($_GET['gameId'] ?? 0);
    
    if (!$gameId) {
        jsonResponse(null, 'ID de partida requerido', false, 400);
    }
    
    $db = Database::getInstance();
    
    // Obtener información de la partida
    $game = $db->selectOne('partidas', '*', 'id = ?', ['id' => $gameId]);
    
    if (!$game) {
        jsonResponse(null, 'Partida no encontrada', false, 404);
    }
    
    // Obtener jugadores
    $players = $db->select('partida_jugadores', '*', 'partida_id = ? ORDER BY orden_juego', 
                          ['partida_id' => $gameId]);
    
    // Obtener movimientos
    $moves = $db->select('partida_dinosaurios', '*', 'partida_id = ? ORDER BY fecha_colocacion', 
                        ['partida_id' => $gameId]);
    
    // Obtener último dado si existe
    $lastDice = $db->selectOne('partida_dados', '*', 'partida_id = ? ORDER BY fecha_lanzamiento DESC LIMIT 1', 
                              ['partida_id' => $gameId]);
    
    // Configuración actual
    $config = json_decode($game['configuracion'], true);
    
    // Preparar respuesta
    $gameStatus = [
        'id' => $game['id'],
        'nombre' => $game['nombre'],
        'estado' => $game['estado'],
        'modo' => $game['modo_juego'],
        'numeroJugadores' => $game['numero_jugadores'],
        'rondaActual' => $game['ronda_actual'],
        'turnoActual' => $game['turno_actual'],
        'jugadorActivo' => $game['jugador_activo'],
        'dadoActual' => $game['dado_actual'],
        'fechaCreacion' => $game['fecha_creacion'],
        'fechaInicio' => $game['fecha_inicio'],
        'fechaFinalizacion' => $game['fecha_finalizacion'],
        'ganador' => $game['ganador_id'],
        'puntuacionMaxima' => $game['puntuacion_maxima'],
        'configuracion' => $config,
        'players' => array_map(function($player) {
            return [
                'id' => $player['id'],
                'usuarioId' => $player['usuario_id'],
                'nombre' => $player['nombre_jugador'],
                'orden' => $player['orden_juego'],
                'puntuacionTotal' => $player['puntuacion_total'],
                'puntuacionR1' => $player['puntuacion_r1'],
                'puntuacionR2' => $player['puntuacion_r2'],
                'dinosauriosTotales' => $player['dinosaurios_totales'],
                'trexBonus' => $player['trex_bonus'],
                'estado' => $player['estado'],
                'tablero' => json_decode($player['tablero_estado'], true)
            ];
        }, $players),
        'moves' => $moves,
        'lastDice' => $lastDice
    ];
    
    jsonResponse($gameStatus, 'Estado de partida obtenido', true, 200);
    
} catch (Exception $e) {
    logError('Error obteniendo estado de partida: ' . $e->getMessage(), [
        'game_id' => $gameId ?? 'unknown',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}
?>

<?php
/**
 * API para finalizar partida
 * /api/game/finish.php
 */

require_once '../config/config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

// Verificar sesión
session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(null, 'Sesión requerida', false, 401);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(null, 'Datos de entrada no válidos', false, 400);
    }
    
    $gameId = (int)($input['gameId'] ?? 0);
    $finalScores = $input['players'] ?? [];
    $winnerId = (int)($input['winner'] ?? 0);
    $maxScore = (int)($input['finalScore'] ?? 0);
    
    if (!$gameId || empty($finalScores)) {
        jsonResponse(null, 'Datos de finalización incompletos', false, 400);
    }
    
    $db = Database::getInstance();
    
    // Verificar que la partida existe
    $game = $db->selectOne('partidas', '*', 'id = ?', ['id' => $gameId]);
    
    if (!$game) {
        jsonResponse(null, 'Partida no encontrada', false, 404);
    }
    
    if ($game['estado'] === 'finalizada') {
        jsonResponse(null, 'La partida ya está finalizada', false, 400);
    }
    
    // Iniciar transacción
    $db->beginTransaction();
    
    try {
        // Actualizar estado de la partida
        $db->update('partidas', [
            'estado' => 'finalizada',
            'fecha_finalizacion' => date('Y-m-d H:i:s'),
            'ganador_id' => $winnerId,
            'puntuacion_maxima' => $maxScore
        ], 'id = ?', ['id' => $gameId]);
        
        // Actualizar puntuaciones de jugadores
        foreach ($finalScores as $playerData) {
            $db->update('partida_jugadores', [
                'puntuacion_total' => (int)$playerData['score'],
                'puntuacion_r1' => (int)($playerData['r1Score'] ?? 0),
                'puntuacion_r2' => (int)($playerData['r2Score'] ?? 0),
                'dinosaurios_totales' => (int)($playerData['totalDinos'] ?? 0),
                'trex_bonus' => (int)($playerData['trexBonus'] ?? 0)
            ], 'id = ?', ['id' => (int)$playerData['id']]);
        }
        
        // Log de finalización
        $logData = [
            'partida_id' => $gameId,
            'jugador_id' => $winnerId,
            'accion' => 'finalizar_partida',
            'descripcion' => "Partida finalizada. Ganador: jugador ID $winnerId con $maxScore puntos",
            'datos_adicionales' => json_encode([
                'scores' => $finalScores,
                'duration' => $input['duration'] ?? null
            ])
        ];
        
        $db->insert('logs_partida', $logData);
        
        // Actualizar estadísticas de usuarios (si aplica)
        updateUserStats($gameId, $finalScores, $winnerId, $db);
        
        $db->commit();
        
        jsonResponse([
            'gameId' => $gameId,
            'status' => 'finalizada',
            'winner' => $winnerId,
            'maxScore' => $maxScore,
            'players' => $finalScores
        ], 'Partida finalizada correctamente', true, 200);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    logError('Error finalizando partida: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'game_id' => $gameId ?? 'unknown',
        'input' => $input ?? []
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}

/**
 * Actualizar estadísticas de usuarios
 */
function updateUserStats($gameId, $finalScores, $winnerId, $db) {
    $players = $db->select('partida_jugadores', '*', 'partida_id = ?', ['partida_id' => $gameId]);
    
    foreach ($players as $player) {
        if (!$player['usuario_id']) continue; // Skip IA players
        
        $isWinner = ($player['id'] == $winnerId);
        $playerScore = 0;
        
        // Encontrar puntuación del jugador
        foreach ($finalScores as $scoreData) {
            if ((int)$scoreData['id'] === (int)$player['id']) {
                $playerScore = (int)$scoreData['score'];
                break;
            }
        }
        
        // Verificar si las estadísticas del usuario ya existen
        $stats = $db->selectOne('usuario_estadisticas', '*', 'usuario_id = ?', 
                               ['usuario_id' => $player['usuario_id']]);
        
        if ($stats) {
            // Actualizar estadísticas existentes
            $newGamesPlayed = $stats['partidas_jugadas'] + 1;
            $newGamesWon = $stats['partidas_ganadas'] + ($isWinner ? 1 : 0);
            $newGamesLost = $stats['partidas_perdidas'] + ($isWinner ? 0 : 1);
            $newMaxScore = max($stats['puntuacion_maxima'], $playerScore);
            $newAvgScore = (($stats['puntuacion_promedio'] * $stats['partidas_jugadas']) + $playerScore) / $newGamesPlayed;
            
            // Actualizar racha de victorias
            $newCurrentStreak = $isWinner ? $stats['racha_victorias_actual'] + 1 : 0;
            $newMaxStreak = max($stats['racha_victorias_maxima'], $newCurrentStreak);
            
            $db->update('usuario_estadisticas', [
                'partidas_jugadas' => $newGamesPlayed,
                'partidas_ganadas' => $newGamesWon,
                'partidas_perdidas' => $newGamesLost,
                'puntuacion_maxima' => $newMaxScore,
                'puntuacion_promedio' => round($newAvgScore, 2),
                'racha_victorias_actual' => $newCurrentStreak,
                'racha_victorias_maxima' => $newMaxStreak
            ], 'usuario_id = ?', ['usuario_id' => $player['usuario_id']]);
            
        } else {
            // Crear nuevas estadísticas
            $db->insert('usuario_estadisticas', [
                'usuario_id' => $player['usuario_id'],
                'partidas_jugadas' => 1,
                'partidas_ganadas' => $isWinner ? 1 : 0,
                'partidas_perdidas' => $isWinner ? 0 : 1,
                'puntuacion_maxima' => $playerScore,
                'puntuacion_promedio' => $playerScore,
                'racha_victorias_actual' => $isWinner ? 1 : 0,
                'racha_victorias_maxima' => $isWinner ? 1 : 0
            ]);
        }
    }
}
?>

<?php
/**
 * API para unirse a partida
 * /api/game/join.php
 */

require_once '../config/config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

// Verificar sesión
session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(null, 'Sesión requerida', false, 401);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(null, 'Datos de entrada no válidos', false, 400);
    }
    
    $gameId = (int)($input['gameId'] ?? 0);
    $playerName = sanitizeInput($input['playerName'] ?? $_SESSION['username']);
    
    if (!$gameId) {
        jsonResponse(null, 'ID de partida requerido', false, 400);
    }
    
    $db = Database::getInstance();
    
    // Verificar que la partida existe y acepta jugadores
    $game = $db->selectOne('partidas', '*', 'id = ?', ['id' => $gameId]);
    
    if (!$game) {
        jsonResponse(null, 'Partida no encontrada', false, 404);
    }
    
    if ($game['estado'] !== 'preparacion') {
        jsonResponse(null, 'La partida ya comenzó', false, 400);
    }
    
    // Verificar si el usuario ya está en la partida
    $existingPlayer = $db->selectOne('partida_jugadores', '*', 
                                    'partida_id = ? AND usuario_id = ?',
                                    ['partida_id' => $gameId, 'usuario_id' => $_SESSION['user_id']]);
    
    if ($existingPlayer) {
        jsonResponse(null, 'Ya estás en esta partida', false, 400);
    }
    
    // Contar jugadores actuales
    $currentPlayers = $db->count('partida_jugadores', 'partida_id = ?', ['partida_id' => $gameId]);
    
    if ($currentPlayers >= $game['numero_jugadores']) {
        jsonResponse(null, 'La partida está llena', false, 400);
    }
    
    // Encontrar el próximo orden disponible
    $nextOrder = $currentPlayers + 1;
    
    // Agregar jugador a la partida
    $playerData = [
        'partida_id' => $gameId,
        'usuario_id' => $_SESSION['user_id'],
        'nombre_jugador' => $playerName,
        'orden_juego' => $nextOrder,
        'tablero_estado' => json_encode([
            1 => [], 2 => [], 3 => [], 4 => [], 5 => [], 6 => [], 7 => []
        ])
    ];
    
    $playerId = $db->insert('partida_jugadores', $playerData);
    
    // Log de unión
    $logData = [
        'partida_id' => $gameId,
        'jugador_id' => $playerId,
        'accion' => 'unirse_partida',
        'descripcion' => "Jugador '$playerName' se unió a la partida",
        'datos_adicionales' => json_encode($playerData)
    ];
    
    $db->insert('logs_partida', $logData);
    
    // Si la partida está completa, cambiar estado a en_curso
    $updatedPlayerCount = $db->count('partida_jugadores', 'partida_id = ?', ['partida_id' => $gameId]);
    
    if ($updatedPlayerCount >= $game['numero_jugadores']) {
        $db->update('partidas', [
            'estado' => 'en_curso',
            'fecha_inicio' => date('Y-m-d H:i:s')
        ], 'id = ?', ['id' => $gameId]);
    }
    
    // Respuesta exitosa
    jsonResponse([
        'playerId' => $playerId,
        'gameId' => $gameId,
        'playerName' => $playerName,
        'order' => $nextOrder,
        'gameStatus' => $updatedPlayerCount >= $game['numero_jugadores'] ? 'en_curso' : 'preparacion',
        'playersCount' => $updatedPlayerCount,
        'maxPlayers' => $game['numero_jugadores']
    ], 'Te uniste a la partida exitosamente', true, 200);
    
} catch (Exception $e) {
    logError('Error uniéndose a partida: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'game_id' => $gameId ?? 'unknown',
        'input' => $input ?? []
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}
?>

<?php
/**
 * API para lanzar dado
 * /api/game/dice.php
 */

require_once '../config/config.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(null, 'Método no permitido', false, 405);
}

// Verificar sesión
session_name(SESSION_NAME);
session_start();

if (!isset($_SESSION['user_id'])) {
    jsonResponse(null, 'Sesión requerida', false, 401);
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        jsonResponse(null, 'Datos de entrada no válidos', false, 400);
    }
    
    $gameId = (int)($input['gameId'] ?? 0);
    $playerId = (int)($input['playerId'] ?? 0);
    $round = (int)($input['round'] ?? 1);
    $turn = (int)($input['turn'] ?? 1);
    
    if (!$gameId || !$playerId) {
        jsonResponse(null, 'Datos incompletos', false, 400);
    }
    
    $db = Database::getInstance();
    
    // Verificar que la partida está activa
    $game = $db->selectOne('partidas', '*', 'id = ? AND estado = ?', 
                          ['id' => $gameId, 'en_curso']);
    
    if (!$game) {
        jsonResponse(null, 'Partida no encontrada o no activa', false, 404);
    }
    
    // Verificar que el jugador pertenece a la partida
    $player = $db->selectOne('partida_jugadores', '*', 
                            'id = ? AND partida_id = ?',
                            ['id' => $playerId, 'partida_id' => $gameId]);
    
    if (!$player) {
        jsonResponse(null, 'Jugador no encontrado en esta partida', false, 403);
    }
    
    // Verificar que no se ha lanzado el dado para esta ronda/turno
    $existingDice = $db->selectOne('partida_dados', '*', 
                                  'partida_id = ? AND ronda = ? AND turno = ?',
                                  ['partida_id' => $gameId, 'ronda' => $round, 'turno' => $turn]);
    
    if ($existingDice) {
        jsonResponse(['dice' => $existingDice], 'El dado ya fue lanzado para este turno', true, 200);
    }
    
    // Lanzar dado (1-6)
    $diceResult = random_int(1, 6);
    
    // Determinar restricción según el resultado
    $restrictions = [
        1 => 'left_zone',      // Zona izquierda
        2 => 'right_zone',     // Zona derecha  
        3 => 'forest_zone',    // Zona boscosa
        4 => 'rocky_zone',     // Zona rocosa
        5 => 'empty_enclosure', // Recinto vacío
        6 => 'no_trex'         // Recinto sin T-Rex
    ];
    
    $restriction = $restrictions[$diceResult];
    
    // Guardar resultado del dado
    $diceData = [
        'partida_id' => $gameId,
        'ronda' => $round,
        'turno' => $turn,
        'jugador_activo_id' => $playerId,
        'cara_dado' => $diceResult,
        'restriccion_aplicada' => $restriction
    ];
    
    $diceId = $db->insert('partida_dados', $diceData);
    
    // Actualizar partida con el resultado del dado
    $db->update('partidas', [
        'dado_actual' => $diceResult,
        'jugador_activo' => $player['orden_juego'] - 1 // 0-indexed
    ], 'id = ?', ['id' => $gameId]);
    
    // Log del lanzamiento
    $logData = [
        'partida_id' => $gameId,
        'jugador_id' => $playerId,
        'accion' => 'lanzar_dado',
        'descripcion' => "Dado lanzado: $diceResult (Restricción: $restriction)",
        'datos_adicionales' => json_encode($diceData)
    ];
    
    $db->insert('logs_partida', $logData);
    
    // Respuesta exitosa
    jsonResponse([
        'diceId' => $diceId,
        'result' => $diceResult,
        'restriction' => $restriction,
        'round' => $round,
        'turn' => $turn,
        'activePlayer' => $player['orden_juego'] - 1
    ], 'Dado lanzado exitosamente', true, 200);
    
} catch (Exception $e) {
    logError('Error lanzando dado: ' . $e->getMessage(), [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'game_id' => $gameId ?? 'unknown',
        'player_id' => $playerId ?? 'unknown'
    ]);
    
    $message = APP_ENV === 'development' ? $e->getMessage() : 'Error interno del servidor';
    jsonResponse(null, $message, false, 500);
}
?>