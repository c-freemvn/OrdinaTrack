<?php
// ============================================
// API front controller / router
// ============================================
declare(strict_types=1);

namespace Ordinatrack\Api;

// Load Composer autoloader
require __DIR__ . '/vendor/autoload.php';

// Initialize environment configuration
use Ordinatrack\Api\Config\Config;
use Ordinatrack\Api\Controller\AuthController;
Config::init();

// 1) Never render internals to a client. Log instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// 2) Buffer output so a stray warning from a deep include can't corrupt the
//    JSON body, and so we can discard partial output on failure.
ob_start();

// 3) Last line of defense: even an UNcatchable fatal (parse error, OOM,
//    timeout) returns clean JSON instead of a half-rendered HTML stack trace.
register_shutdown_function(static function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log("Fatal: {$e['message']} in {$e['file']}:{$e['line']}");
        if (!headers_sent()) {
            @ob_end_clean();
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['statuscode' => 500, 'status' => 'Internal server error']);
        }
    }
});

// --- Resources whose routes are reachable WITHOUT a valid session. ---
// Everything else is authenticated by the middleware below. Only `auth`
// carries unauthenticated endpoints (login, register, reset, verify OTP...).
const PUBLIC_RESOURCES = ['auth'];

// --- Route resources that map to a {name}.route.php file + {name}Routes() handler. ---
$ALLOWED_ROUTES = ['admin', 'auth', 'folder', 'logistics', 'privilege', 'public', 'requests'];

// One responder: consistent shape + headers, discard buffer, stop.
function respond(int $http, array $body)
{
    if (!headers_sent()) {
        http_response_code($http);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    @ob_end_clean();
    echo json_encode($body);
    exit;
}

// Read the request payload by method, WITHOUT mutating it.
function getRequestData(): array
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'GET' || $method === 'DELETE') {
        return $_GET;
    }
    $ctype = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ctype, 'application/json') !== false) {
        $decoded = json_decode(file_get_contents('php://input') ?: '', true);
        return is_array($decoded) ? $decoded : [];
    }
    if ($method === 'POST') {
        return $_POST; // form-urlencoded / multipart ($_FILES handled separately)
    }
    parse_str(file_get_contents('php://input') ?: '', $put); // PUT/PATCH form bodies
    return $put;
}

// --- Session hardening (before session_start) ---
ini_set('session.cookie_secure',    '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly',  '1');
ini_set('session.cookie_samesite',  'Strict'); // switch to 'Lax' + CSRF token if the SPA is a different origin
ini_set('session.use_strict_mode',  '1');
ini_set('session.gc_maxlifetime',   '3600');   // server-side idle timeout
session_start();
if (!isset($_SESSION['last_regeneration'])) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

try {
    // Resolve the path relative to this script (works from a subdirectory).
    $uri = explode('?', $_SERVER['REQUEST_URI'] ?? '/')[0];
    if (!empty($_SERVER['PATH_INFO'])) {
        $uri = $_SERVER['PATH_INFO'];
    } else {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if ($script !== '' && strpos($uri, $script) === 0) {
            $uri = substr($uri, strlen($script));
        }
    }
    $segments = array_values(array_filter(explode('/', trim($uri, '/')), 'strlen'));

    if (count($segments) !== 2) {
        respond(404, [
            'statuscode' => 404,
            'status' => 'Invalid endpoint',
            'message' => 'Expected format: /{resource}/{action}'
        ]);
    }
    [$resource, $action] = $segments;

    // Fail CLOSED: reject invalid input, never silently rewrite it.
    if (!in_array($resource, $ALLOWED_ROUTES, true)) {
        respond(404, ['statuscode' => 404, 'status' => 'Route not found']);
    }
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $action)) {
        respond(400, ['statuscode' => 400, 'status' => 'Invalid action']);
    }

    // Contain the include path.
    $routeDir = __DIR__ . '/API/src/Routes/';
    $realDir  = realpath($routeDir);
    $fullpath = realpath($routeDir . $resource . '.route.php');
    if (
        $fullpath === false || $realDir === false ||
        strpos($fullpath, $realDir . DIRECTORY_SEPARATOR) !== 0
    ) {
        respond(404, ['statuscode' => 404, 'status' => 'Route not found']);
    }

    // Set CWD to /routes so model.php's relative requires resolve.
    chdir($routeDir);

    $data = getRequestData();

    // --- AUTH MIDDLEWARE ---
    // Every resource except the public ones requires a valid session.
    if (!in_array($resource, PUBLIC_RESOURCES, true)) {
        require_once __DIR__ . '/API/src/Controller/AuthController.php';
        if (!(new AuthController($data))->verifyToken()) {
            respond(200, ['statuscode' => 99, 'status' => 'Unauthorized or session has expired']);
        }
    }

    require_once $fullpath;

    $handler = $resource . 'Routes';
    if (!function_exists($handler)) {
        respond(500, ['statuscode' => 500, 'status' => 'Route handler not configured']);
    }

    $response = $handler($action, $data);

    // Handlers already return an encoded JSON string; emit as-is.
    @ob_end_clean();
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }
    echo is_string($response) ? $response : json_encode($response);
} catch (\Throwable $e) {
    error_log("Routing error: {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}");
    respond(500, ['statuscode' => 500, 'status' => 'Internal server error']);
}
