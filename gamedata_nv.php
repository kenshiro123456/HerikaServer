<?php
/**
 * New Vegas Game Data Adapter Endpoint
 * 
 * Handles JSON POST requests from Fallout: New Vegas (via d3d9_hook.cpp)
 * and translates them to Skyrim-compatible format for HerikaServer.
 * 
 * This adapter allows New Vegas to use HerikaServer without modifying
 * the core server code.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1'); // Enable error display for debugging
require_once(__DIR__ . "/conf/conf.php");
require_once(__DIR__ . "/lib/postgresql_nv.class.php");
$GLOBALS["db"] = new sql_nv();
require_once(__DIR__ . "/lib/logger.php");
require_once(__DIR__ . "/lib/utils_game_timestamp.php");

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit;
}

// Parse JSON body
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Check for JSON parsing errors
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    $errorMsg = json_last_error_msg();
    echo "Bad Request: Invalid JSON - {$errorMsg}";
    $truncatedPayload = strlen($json) > 1024 ? substr($json, 0, 1024) . '...' : $json;
    Logger::error("[gamedata_nv.php] Invalid JSON received. Error: {$errorMsg}. Payload: {$truncatedPayload}");
    exit;
}

// Check for missing type field
if (!$data || !isset($data['type'])) {
    http_response_code(400);
    echo "Bad Request: Missing type field";
    $truncatedPayload = strlen($json) > 1024 ? substr($json, 0, 1024) . '...' : $json;
    Logger::error("[gamedata_nv.php] Bad request - missing type field. Payload: {$truncatedPayload}");
    exit;
}

Logger::debug("[gamedata_nv.php] Received event type: {$data['type']}");

try {
    switch ($data['type']) {
        case 'subtitle':
            handleSubtitleEvent($data);
            break;
        case 'location':
            handleLocationEvent($data);
            break;
        case 'user_input':
            handleUserInputEvent($data);
            break;
        default:
            http_response_code(400);
            echo "Bad Request: Unknown type";
            Logger::error("[gamedata_nv.php] Bad request - unknown type: {$data['type']}");
            exit;
    }
    
    echo "OK";
} catch (Exception $e) {
    http_response_code(500);
    echo "Internal Server Error";
    Logger::error("[gamedata_nv.php] Error processing request: " . $e->getMessage());
}

/**
 * Handle subtitle event from New Vegas
 * Translates to Skyrim format and forwards to main.php
 */
function handleSubtitleEvent(array $data): void {
    // Validate required fields
    $requiredFields = ['speaker_name', 'text', 'timestamp'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        http_response_code(400);
        echo "Bad Request: Missing required fields: " . implode(', ', $missingFields);
        Logger::error("[gamedata_nv.php] Subtitle event missing fields: " . implode(', ', $missingFields));
        exit;
    }
    
    // Translate to Skyrim format
    try {
        $skyrimData = translateSubtitle($data);
    } catch (Exception $e) {
        http_response_code(500);
        echo "Internal Server Error: Translation failed";
        Logger::error("[gamedata_nv.php] Translation error for subtitle event. Field: {$e->getMessage()}. Event type: subtitle");
        exit;
    }
    
    // Log received event with type and actor_name
    Logger::debug("[gamedata_nv.php] Received event - Type: subtitle, Actor: {$skyrimData['actor_name']}");
    
    // Forward to main.php
    forwardToMainEndpoint($skyrimData);
    
    Logger::debug("[gamedata_nv.php] Processed subtitle event from: {$data['speaker_name']}");
}

/**
 * Handle location event from New Vegas
 * Translates to Skyrim format and stores in database
 */
function handleLocationEvent(array $data): void {
    // Validate required fields
    $requiredFields = ['cell', 'timestamp'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        http_response_code(400);
        echo "Bad Request: Missing required fields: " . implode(', ', $missingFields);
        Logger::error("[gamedata_nv.php] Location event missing fields: " . implode(', ', $missingFields));
        exit;
    }
    
    // Translate to Skyrim format
    try {
        $skyrimData = translateLocation($data);
    } catch (Exception $e) {
        http_response_code(500);
        echo "Internal Server Error: Translation failed";
        Logger::error("[gamedata_nv.php] Translation error for location event. Field: {$e->getMessage()}. Event type: location");
        exit;
    }
    
    // Log received event with type and actor_name
    Logger::debug("[gamedata_nv.php] Received event - Type: location, Actor: {$skyrimData['actor_name']}");
    
    // Debug: Check database class before storeLocationData
    Logger::debug("[gamedata_nv.php] DB class before storeLocationData: " . get_class($GLOBALS["db"]));
    
    // Store location data in core_player table
    storeLocationData($skyrimData);
    
    // Debug: Check database class after storeLocationData
    Logger::debug("[gamedata_nv.php] DB class after storeLocationData: " . get_class($GLOBALS["db"]));
    
    // Also store in eventlog table so DataLastKnownLocation() can find it
    // Format: (Context location: <cell> ,Hold: <worldspace>, buildings to go:,, Current Date in Skyrim World: ...)
    $locationString = "(Context location: {$skyrimData['cell']} ,Hold: {$skyrimData['worldspace']}, buildings to go:,, Current Date in Skyrim World: " . convert_gamets2skyrim_date($skyrimData['ts']) . ")";
    
    $GLOBALS["db"]->insert(
        'eventlog',
        array(
            'ts' => $skyrimData['ts'],
            'gamets' => $skyrimData['ts'],
            'type' => 'location',
            'data' => $locationString,
            'sess' => 'pending',
            'localts' => time(),
            'people' => '',
            'location' => $locationString,
            'party' => ''
        )
    );
    
    Logger::debug("[gamedata_nv.php] Processed location event: {$data['cell']}");
}

/**
 * Handle user input event from New Vegas
 * Translates to Skyrim format and forwards to main.php
 */
function handleUserInputEvent(array $data): void {
    // Validate required fields
    $requiredFields = ['input', 'timestamp'];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        http_response_code(400);
        echo "Bad Request: Missing required fields: " . implode(', ', $missingFields);
        Logger::error("[gamedata_nv.php] User input event missing fields: " . implode(', ', $missingFields));
        exit;
    }
    
    // Translate to Skyrim format
    try {
        $skyrimData = translateUserInput($data);
    } catch (Exception $e) {
        http_response_code(500);
        echo "Internal Server Error: Translation failed";
        Logger::error("[gamedata_nv.php] Translation error for user_input event. Field: {$e->getMessage()}. Event type: user_input");
        exit;
    }
    
    // Log received event with type and actor_name
    Logger::debug("[gamedata_nv.php] Received event - Type: user_input, Actor: {$skyrimData['actor_name']}");
    
    // Forward to main.php
    forwardToMainEndpoint($skyrimData);
    
    Logger::debug("[gamedata_nv.php] Processed user input event");
}

/**
 * Translate New Vegas subtitle event to Skyrim format
 */
function translateSubtitle(array $nvData): array {
    // Validate critical fields before translation
    if (empty($nvData['speaker_name'])) {
        throw new Exception('speaker_name');
    }
    if (empty($nvData['text'])) {
        throw new Exception('text');
    }
    if (!isset($nvData['timestamp'])) {
        throw new Exception('timestamp');
    }
    
    // Convert timestamp to Unix timestamp
    $unixTimestamp = convertTimestamp($nvData['timestamp']);
    
    return [
        'actor_name' => $nvData['speaker_name'],
        'dialogue_text' => $nvData['text'],
        'refid' => isset($nvData['speaker_refid']) ? $nvData['speaker_refid'] : '',
        'baseid' => isset($nvData['speaker_baseid']) ? $nvData['speaker_baseid'] : '',
        'location' => isset($nvData['cell']) ? $nvData['cell'] : '',
        'ts' => $unixTimestamp,
        'game_ts' => $nvData['timestamp'],
        'nv_metadata' => [
            'cellFormID' => isset($nvData['cellFormID']) ? $nvData['cellFormID'] : '',
            'topicinfo_id' => isset($nvData['topicinfo_id']) ? $nvData['topicinfo_id'] : ''
        ]
    ];
}

/**
 * Translate New Vegas location event to Skyrim format
 */
function translateLocation(array $nvData): array {
    // Validate critical fields before translation
    if (empty($nvData['cell'])) {
        throw new Exception('cell');
    }
    if (!isset($nvData['timestamp'])) {
        throw new Exception('timestamp');
    }
    
    // Convert timestamp to Unix timestamp
    $unixTimestamp = convertTimestamp($nvData['timestamp']);
    
    return [
        'actor_name' => 'Player',
        'location' => isset($nvData['location']) ? $nvData['location'] : $nvData['cell'],
        'cell' => $nvData['cell'],
        'worldspace' => isset($nvData['worldspace']) ? $nvData['worldspace'] : '',
        'position_x' => isset($nvData['pos']['x']) ? floatval($nvData['pos']['x']) : 0.0,
        'position_y' => isset($nvData['pos']['y']) ? floatval($nvData['pos']['y']) : 0.0,
        'position_z' => isset($nvData['pos']['z']) ? floatval($nvData['pos']['z']) : 0.0,
        'interior' => isset($nvData['interior']) ? boolval($nvData['interior']) : false,
        'ts' => $unixTimestamp,
        'game_ts' => $nvData['timestamp'],
        'nv_metadata' => [
            'cellFormID' => isset($nvData['cellFormID']) ? $nvData['cellFormID'] : '',
            'worldspaceFormID' => isset($nvData['worldspaceFormID']) ? $nvData['worldspaceFormID'] : ''
        ]
    ];
}

/**
 * Translate New Vegas user input event to Skyrim format
 */
function translateUserInput(array $nvData): array {
    // Validate critical fields before translation
    if (empty($nvData['input'])) {
        throw new Exception('input');
    }
    if (!isset($nvData['timestamp'])) {
        throw new Exception('timestamp');
    }
    
    // Convert timestamp to Unix timestamp
    $unixTimestamp = convertTimestamp($nvData['timestamp']);
    
    $result = [
        'actor_name' => 'Player',
        'input_text' => $nvData['input'],
        'ts' => $unixTimestamp,
        'game_ts' => $nvData['timestamp'],
        'nv_metadata' => []
    ];
    
    // Add location data if present
    if (isset($nvData['location'])) {
        $result['location'] = $nvData['location'];
        $result['nv_metadata']['location'] = $nvData['location'];
    }
    
    // Add target data if present
    if (isset($nvData['target'])) {
        $result['target'] = $nvData['target'];
        $result['nv_metadata']['target'] = $nvData['target'];
    }
    
    return $result;
}

/**
 * Convert New Vegas timestamp to Unix timestamp
 * Input: Unix timestamp (integer)
 * Output: Unix timestamp (integer) - passthrough
 */
function convertTimestamp($nvTimestamp): int {
    // If already an integer (Unix timestamp), return as-is
    if (is_int($nvTimestamp)) {
        return $nvTimestamp;
    }
    
    // If it's a numeric string, convert to integer
    if (is_numeric($nvTimestamp)) {
        return intval($nvTimestamp);
    }
    
    // Fallback: try to parse as datetime string (for backward compatibility)
    $dt = DateTime::createFromFormat('Y-m-d H:i:s.u', $nvTimestamp);
    
    if ($dt === false) {
        // Fallback: try without milliseconds
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $nvTimestamp);
    }
    
    if ($dt === false) {
        // Fallback: use current time
        Logger::warn("[gamedata_nv.php] Failed to parse timestamp: {$nvTimestamp}, using current time");
        return time();
    }
    
    return $dt->getTimestamp();
}

/**
 * Forward translated data to HerikaServer's main.php endpoint
 * Format: "inputtext|<unix_ts>|<game_ts>|<actor_name>: <text>"
 */
function forwardToMainEndpoint(array $skyrimData): void {
    // Format the data string for main.php
    // Format: "inputtext|<unix_ts>|<unix_ts>|<actor_name>: <text>"
    // Note: main.php expects both ts and gamets as Unix timestamps (integers)
    $actorName = $skyrimData['actor_name'] ?? 'Unknown';
    $text = $skyrimData['dialogue_text'] ?? $skyrimData['input_text'] ?? '';
    $unixTs = $skyrimData['ts'] ?? time();
    
    // Use Unix timestamp for both ts and gamets (main.php expects integers)
    $dataString = "inputtext|{$unixTs}|{$unixTs}|{$actorName}: {$text}";
    
    // Base64 encode the data string
    $encodedData = base64_encode($dataString);
    
    // Build the URL for main.php
    $mainPhpUrl = __DIR__ . "/main.php";
    
    // Call main.php via internal function call (include)
    // We need to simulate the GET request by setting $_SERVER and $_GET
    $originalQueryString = $_SERVER['QUERY_STRING'] ?? '';
    $originalGet = $_GET;
    
    try {
        // Set up the environment for main.php
        $_SERVER['QUERY_STRING'] = "DATA={$encodedData}";
        $_GET['DATA'] = $encodedData;
        
        // Capture output from main.php
        ob_start();
        include($mainPhpUrl);
        $output = ob_get_clean();
        
        Logger::debug("[gamedata_nv.php] Forwarded to main.php: {$actorName}");
        
    } catch (Exception $e) {
        Logger::error("[gamedata_nv.php] Error forwarding to main.php: " . $e->getMessage());
        throw $e;
    } finally {
        // Restore original environment
        $_SERVER['QUERY_STRING'] = $originalQueryString;
        $_GET = $originalGet;
    }
}

/**
 * Store location data in HerikaServer database
 */
function storeLocationData(array $skyrimData): void {
    try {
        require_once(__DIR__ . "/lib/core/player.class.php");
        $player = new Player();
        
        // Store location data in core_player table
        $locationData = [
            'location' => $skyrimData['location'] ?? '',
            'cell' => $skyrimData['cell'] ?? '',
            'worldspace' => $skyrimData['worldspace'] ?? '',
            'position_x' => $skyrimData['position_x'] ?? 0.0,
            'position_y' => $skyrimData['position_y'] ?? 0.0,
            'position_z' => $skyrimData['position_z'] ?? 0.0,
            'interior' => $skyrimData['interior'] ?? false,
            'ts' => $skyrimData['ts'] ?? time(),
            'game_ts' => $skyrimData['game_ts'] ?? ''
        ];
        
        // Include New Vegas metadata if present
        if (isset($skyrimData['nv_metadata'])) {
            $locationData['nv_metadata'] = $skyrimData['nv_metadata'];
        }
        
        // Store as JSON in core_player table with key 'location'
        $player->setJson('location', $locationData);
        
        Logger::debug("[gamedata_nv.php] Stored location data: {$locationData['location']}");
        
    } catch (Exception $e) {
        Logger::error("[gamedata_nv.php] Failed to store location data: " . $e->getMessage());
        throw $e;
    }
}
