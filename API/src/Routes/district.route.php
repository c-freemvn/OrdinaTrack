<?php

/**
 * District Routes
 *
 * Endpoints for the signed-in user's own district: the district whose administrator is
 * the current user (set by the super admin under Locations). $action is the path after
 * /district, e.g. "branches" or "branches/5".
 *
 *   GET    info                                       the district + headline numbers
 *   GET    activity?limit=                            audit trail for its administrators
 *   GET    branches?search=&status=&limit=&offset=
 *   POST   branches
 *   GET    branches/{id}
 *   PUT    branches/{id}
 *   DELETE branches/{id}                              (refused while it has members)
 *   GET    members?search=&status=&role=&branch_id=&limit=&offset=   (read-only)
 */

require_once __DIR__ . '/../Controller/DistrictController.php';
require_once __DIR__ . '/../Middleware/SuperAdminMiddleware.php';

use Ordinatrack\Api\Controller\DistrictController;
use Ordinatrack\Api\Middleware\SuperAdminMiddleware;

/**
 * Route handler for district endpoints
 *
 * @param string $action The path after /district
 * @param array $data The request data (query string for GET/DELETE, body otherwise)
 * @return string JSON response
 */
function districtRoutes($action, $data)
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
        ['GET',    '#^info$#',           'getDistrict'],
        ['GET',    '#^activity$#',       'getActivity'],
        ['GET',    '#^branches$#',       'getBranches'],
        ['POST',   '#^branches$#',       'createBranch'],
        ['GET',    '#^branches/(\d+)$#', 'getBranch'],
        ['PUT',    '#^branches/(\d+)$#', 'updateBranch'],
        ['DELETE', '#^branches/(\d+)$#', 'deleteBranch'],
        ['GET',    '#^members$#',        'getMembers'],
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
            ? DistrictController::$handler($user, $data)
            : DistrictController::$handler($user, $data, $id);
        break;
    }

    if ($response === null) {
        $response = $pathMatched
            ? ['success' => false, 'message' => "Method $method not allowed", 'status' => 405]
            : ['success' => false, 'message' => 'Route not found', 'action' => $action, 'status' => 404];
    }

    // Audit trail (the app's audit logger lives in SuperAdminMiddleware): record
    // successful branch changes only, since reads would drown out real activity
    if ($method !== 'GET' && !empty($response['success'])) {
        $logged = $data;
        unset($logged['token']);

        SuperAdminMiddleware::logAccess(
            $user,
            ['POST' => 'CREATE', 'PUT' => 'UPDATE', 'DELETE' => 'DELETE'][$method] ?? $method,
            'branches',
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
