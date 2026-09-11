<?php

/**
 * Super Admin Routes
 *
 * All routes require authentication and super admin privileges.
 * $action is the path after /admin, e.g. "roles", "roles/5", "users/3/roles".
 *
 *   GET    stats                     GET    permissions
 *   GET    activity?limit=           GET    permissions/by-resource
 *   GET    roles                     POST   permissions
 *   POST   roles                     PUT    permissions/{id}
 *   GET    roles/{id}                DELETE permissions/{id}
 *   PUT    roles/{id}                GET    users?limit=&offset=&search=
 *   DELETE roles/{id}                POST   users
 *   PUT    roles/{id}/permissions    GET|PUT|DELETE users/{id}
 *                                    PUT    users/{id}/roles|status|password
 *
 *   GET|POST   {provinces|districts|branches}   (GET filters: province_id, district_id, search)
 *   PUT|DELETE {provinces|districts|branches}/{id}
 */

require_once __DIR__ . '/../Controller/SuperAdminController.php';
require_once __DIR__ . '/../Middleware/SuperAdminMiddleware.php';

use Ordinatrack\Api\Controller\SuperAdminController;
use Ordinatrack\Api\Middleware\SuperAdminMiddleware;

/**
 * Route handler for admin endpoints
 *
 * @param string $action The path after /admin
 * @param array $data The request data (query string for GET/DELETE, body otherwise)
 * @return string JSON response
 */
function adminRoutes($action, $data)
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    // Get the authenticated user from session
    $user = isset($_SESSION['user']) ? (object)$_SESSION['user'] : null;

    // Verify super admin access
    if (!SuperAdminMiddleware::isSuperAdmin($user)) {
        http_response_code(403);
        return json_encode([
            'success' => false,
            'message' => 'Unauthorized: Only super admin can access this endpoint',
            'statuscode' => 403
        ]);
    }

    // [HTTP method, path pattern, controller handler]
    $routes = [
        ['GET',    '#^stats$#',                   'getSystemStats'],
        ['GET',    '#^activity$#',                'getRecentActivity'],

        ['GET',    '#^roles$#',                   'getRoles'],
        ['POST',   '#^roles$#',                   'createRole'],
        ['GET',    '#^roles/(\d+)$#',             'getRole'],
        ['PUT',    '#^roles/(\d+)$#',             'updateRole'],
        ['DELETE', '#^roles/(\d+)$#',             'deleteRole'],
        ['PUT',    '#^roles/(\d+)/permissions$#', 'setRolePermissions'],
        ['POST',   '#^roles/(\d+)/permissions$#', 'setRolePermissions'], // legacy alias

        ['GET',    '#^permissions$#',             'getPermissions'],
        ['GET',    '#^permissions/by-resource$#', 'getPermissionsByResource'],
        ['POST',   '#^permissions$#',             'createPermission'],
        ['PUT',    '#^permissions/(\d+)$#',       'updatePermission'],
        ['DELETE', '#^permissions/(\d+)$#',       'deletePermission'],

        ['GET',    '#^users$#',                   'getUsers'],
        ['POST',   '#^users$#',                   'createUser'],
        ['GET',    '#^users/(\d+)$#',             'getUser'],
        ['PUT',    '#^users/(\d+)$#',             'updateUser'],
        ['DELETE', '#^users/(\d+)$#',             'deleteUser'],
        ['PUT',    '#^users/(\d+)/roles$#',       'setUserRoles'],
        ['PUT',    '#^users/(\d+)/status$#',      'setUserStatus'],
        ['PUT',    '#^users/(\d+)/password$#',    'resetUserPassword'],
    ];

    // Provinces, districts and branches share handlers; the 4th element is the location type
    foreach (['provinces', 'districts', 'branches'] as $type) {
        $routes[] = ['GET',    "#^$type$#",       'listLocations',  $type];
        $routes[] = ['POST',   "#^$type$#",       'createLocation', $type];
        $routes[] = ['PUT',    "#^$type/(\d+)$#", 'updateLocation', $type];
        $routes[] = ['DELETE', "#^$type/(\d+)$#", 'deleteLocation', $type];
    }

    $response = null;
    $pathMatched = false;
    $id = null;

    foreach ($routes as $route) {
        [$verb, $pattern, $handler] = $route;
        if (!preg_match($pattern, $action, $m)) {
            continue;
        }
        $pathMatched = true;
        if ($verb !== $method) {
            continue;
        }

        // Handler arguments: user, data, then the location type and/or numeric id when present
        $id = isset($m[1]) ? (int)$m[1] : null;
        $args = [$user, $data];
        if (isset($route[3])) {
            $args[] = $route[3];
        }
        if ($id !== null) {
            $args[] = $id;
        }
        $response = SuperAdminController::$handler(...$args);
        break;
    }

    if ($response === null) {
        $response = $pathMatched
            ? ['success' => false, 'message' => "Method $method not allowed", 'status' => 405]
            : ['success' => false, 'message' => 'Route not found', 'action' => $action, 'status' => 404];
    }

    // Audit trail: record successful changes only (reads would drown out real activity)
    if ($method !== 'GET' && !empty($response['success'])) {
        $parts = explode('/', $action);
        $resource = $parts[0] . (isset($parts[2]) ? '.' . $parts[2] : ''); // e.g. "users.roles"
        $resourceId = $id ?? ($response['data']['id'] ?? null);
        $logged = $data;
        unset($logged['password'], $logged['token']);

        SuperAdminMiddleware::logAccess(
            $user,
            ['POST' => 'CREATE', 'PUT' => 'UPDATE', 'DELETE' => 'DELETE'][$method] ?? $method,
            $resource,
            $resourceId !== null ? (int)$resourceId : null,
            $logged ?: null
        );
    }

    // Handle HTTP status codes from response
    $statusCode = $response['status'] ?? 200;
    unset($response['status']);

    if (!headers_sent()) {
        http_response_code($statusCode);
    }

    return json_encode($response);
}
