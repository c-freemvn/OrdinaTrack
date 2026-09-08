<?php

/**
 * Super Admin Routes
 * 
 * All routes require authentication and super admin privileges
 * Routes handle role management, permission management, user management, and system statistics
 */

require_once __DIR__ . '/../Controller/SuperAdminController.php';
require_once __DIR__ . '/../Middleware/SuperAdminMiddleware.php';

use Ordinatrack\Api\Controller\SuperAdminController;
use Ordinatrack\Api\Middleware\SuperAdminMiddleware;

/**
 * Route handler for admin endpoints
 * 
 * @param string $action The action name from the URL
 * @param array $data The request data (GET/POST/PUT parameters)
 * @return string JSON response
 */
function adminRoutes($action, $data)
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    
    // Parse the request body for POST/PUT/DELETE
    $body = null;
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $body = json_decode(file_get_contents('php://input') ?: '{}');
        } else {
            $body = (object)$data;
        }
    }

    // Get the authenticated user from session
    $user = isset($_SESSION['user']) ? (object)$_SESSION['user'] : null;

    // Verify super admin access
    if (!SuperAdminMiddleware::isSuperAdmin($user)) {
        return json_encode([
            'success' => false,
            'message' => 'Unauthorized: Only super admin can access this endpoint',
            'statuscode' => 403,
            'status' => 403
        ]);
    }

    // Log access
    SuperAdminMiddleware::logAccess($user, 'ACCESS', 'admin_api', null, [
        'action' => $action,
        'method' => $method
    ]);

    // Route handling with parameter extraction
    $routes = [
        // Roles
        'roles' => ['method' => 'GET', 'handler' => 'getRoles'],
        'roles-get' => ['method' => 'GET', 'handler' => 'getRole', 'param' => true],
        'roles-post' => ['method' => 'POST', 'handler' => 'createRole'],
        'roles-put' => ['method' => 'PUT', 'handler' => 'updateRole', 'param' => true],
        'roles-delete' => ['method' => 'DELETE', 'handler' => 'deleteRole', 'param' => true],

        // Permissions
        'permissions' => ['method' => 'GET', 'handler' => 'getPermissions'],
        'permissions-resource' => ['method' => 'GET', 'handler' => 'getPermissionsByResource'],
        'permissions-get' => ['method' => 'GET', 'handler' => null],  // Handled by ID pattern
        'permissions-post' => ['method' => 'POST', 'handler' => 'createPermission'],
        'permissions-put' => ['method' => 'PUT', 'handler' => 'updatePermission', 'param' => true],
        'permissions-delete' => ['method' => 'DELETE', 'handler' => 'deletePermission', 'param' => true],

        // System
        'stats' => ['method' => 'GET', 'handler' => 'getSystemStats'],
        'activity' => ['method' => 'GET', 'handler' => 'getRecentActivity'],

        // Users
        'users' => ['method' => 'GET', 'handler' => 'getUsers'],
        'users-single' => ['method' => 'GET', 'handler' => 'getUser', 'param' => true],
    ];

    // Smart routing based on action and method
    switch (true) {
        // Roles endpoints
        case $action === 'roles' && $method === 'GET':
            $response = SuperAdminController::getRoles($user, $method, $body);
            break;
        case $action === 'roles' && $method === 'POST':
            $response = SuperAdminController::createRole($user, $method, $body);
            break;
        case preg_match('/^roles\/(\d+)$/', $action, $m) && $method === 'GET':
            $response = SuperAdminController::getRole($user, $method, $body, $m[1]);
            break;
        case preg_match('/^roles\/(\d+)$/', $action, $m) && $method === 'PUT':
            $response = SuperAdminController::updateRole($user, $method, $body, $m[1]);
            break;
        case preg_match('/^roles\/(\d+)$/', $action, $m) && $method === 'DELETE':
            $response = SuperAdminController::deleteRole($user, $method, $body, $m[1]);
            break;
        case preg_match('/^roles\/(\d+)\/permissions$/', $action, $m) && $method === 'POST':
            $response = SuperAdminController::assignPermissionsToRole($user, $method, $body, $m[1]);
            break;

        // Permissions endpoints
        case $action === 'permissions' && $method === 'GET':
            $response = SuperAdminController::getPermissions($user, $method, $body);
            break;
        case $action === 'permissions/by-resource' && $method === 'GET':
            $response = SuperAdminController::getPermissionsByResource($user, $method, $body);
            break;
        case $action === 'permissions' && $method === 'POST':
            $response = SuperAdminController::createPermission($user, $method, $body);
            break;
        case preg_match('/^permissions\/(\d+)$/', $action, $m) && $method === 'GET':
            $response = ['success' => false, 'message' => 'Use /permissions to get all'];
            break;
        case preg_match('/^permissions\/(\d+)$/', $action, $m) && $method === 'PUT':
            $response = SuperAdminController::updatePermission($user, $method, $body, $m[1]);
            break;
        case preg_match('/^permissions\/(\d+)$/', $action, $m) && $method === 'DELETE':
            $response = SuperAdminController::deletePermission($user, $method, $body, $m[1]);
            break;

        // Users endpoints
        case $action === 'users' && $method === 'GET':
            $response = SuperAdminController::getUsers($user, $method, $body);
            break;
        case $action === 'users' && $method === 'POST':
            $response = ['success' => false, 'message' => 'Use /auth/register to create users'];
            break;
        case preg_match('/^users\/(\d+)$/', $action, $m) && $method === 'GET':
            $response = SuperAdminController::getUser($user, $method, $body, $m[1]);
            break;
        case preg_match('/^users\/(\d+)\/roles$/', $action, $m) && $method === 'PUT':
            $response = SuperAdminController::assignRolesToUser($user, $method, $body, $m[1]);
            break;
        case preg_match('/^users\/(\d+)\/status$/', $action, $m) && $method === 'PUT':
            $response = SuperAdminController::updateUserStatus($user, $method, $body, $m[1]);
            break;
        case preg_match('/^users\/(\d+)$/', $action, $m) && $method === 'DELETE':
            $response = SuperAdminController::deleteUser($user, $method, $body, $m[1]);
            break;

        // System endpoints
        case $action === 'stats' && $method === 'GET':
            $response = SuperAdminController::getSystemStats($user, $method, $body);
            break;
        case $action === 'activity' && $method === 'GET':
            $response = SuperAdminController::getRecentActivity($user, $method, $body);
            break;

        default:
            $response = [
                'statuscode' => 404,
                'status' => 'Route not found',
                'action' => $action,
                'method' => $method
            ];
            break;
    }

    // Handle HTTP status codes from response
    $statusCode = 200;
    if (isset($response['status']) && is_int($response['status'])) {
        $statusCode = $response['status'];
        unset($response['status']);
    }

    if (!headers_sent()) {
        http_response_code($statusCode);
    }

    return json_encode($response);
}
