<?php

/**
 * Branch Routes
 *
 * Endpoints for the signed-in user's own branch: the branch whose administrator is the
 * current user (set by the super admin under Locations). $action is the path after
 * /branch, e.g. "members" or "members/5".
 *
 *   GET    info                                           the branch + member roles
 *   GET    members?search=&status=&role=&limit=&offset=
 *   POST   members
 *   GET    members/{id}
 *   PUT    members/{id}
 *   DELETE members/{id}                                   (soft delete)
 */

require_once __DIR__ . '/../Controller/BranchController.php';
require_once __DIR__ . '/../Middleware/SuperAdminMiddleware.php';

use Ordinatrack\Api\Controller\BranchController;
use Ordinatrack\Api\Middleware\SuperAdminMiddleware;

/**
 * Route handler for branch endpoints
 *
 * @param string $action The path after /branch
 * @param array $data The request data (query string for GET/DELETE, body otherwise)
 * @return string JSON response
 */
function branchRoutes($action, $data)
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

    // Set by the auth middleware in index.php
    $user = isset($_SESSION['user']) ? (object)$_SESSION['user'] : null;
    if (!$user || empty($user->id)) {
        http_response_code(401);
        return json_encode(['success' => false, 'message' => 'Unauthorized', 'statuscode' => 99]);
    }

    // [HTTP method, path pattern, controller handler]
    $routes = [
        ['GET',    '#^info$#',          'getBranch'],
        ['GET',    '#^members$#',       'getMembers'],
        ['POST',   '#^members$#',       'createMember'],
        ['GET',    '#^members/(\d+)$#', 'getMember'],
        ['PUT',    '#^members/(\d+)$#', 'updateMember'],
        ['DELETE', '#^members/(\d+)$#', 'deleteMember'],
    ];

    $response = null;
    $pathMatched = false;
    $id = null;

    foreach ($routes as [$verb, $pattern, $handler]) {
        if (!preg_match($pattern, $action, $m)) {
            continue;
        }
        $pathMatched = true;
        if ($verb !== $method) {
            continue;
        }

        $id = isset($m[1]) ? (int)$m[1] : null;
        $response = $id === null
            ? BranchController::$handler($user, $data)
            : BranchController::$handler($user, $data, $id);
        break;
    }

    if ($response === null) {
        $response = $pathMatched
            ? ['success' => false, 'message' => "Method $method not allowed", 'status' => 405]
            : ['success' => false, 'message' => 'Route not found', 'action' => $action, 'status' => 404];
    }

    // Audit trail (the app's audit logger lives in SuperAdminMiddleware): record
    // successful member changes only, since reads would drown out real activity
    if ($method !== 'GET' && !empty($response['success'])) {
        $logged = $data;
        unset($logged['token']);

        SuperAdminMiddleware::logAccess(
            $user,
            ['POST' => 'CREATE', 'PUT' => 'UPDATE', 'DELETE' => 'DELETE'][$method] ?? $method,
            'members',
            $id ?? ($response['data']['id'] ?? null),
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
