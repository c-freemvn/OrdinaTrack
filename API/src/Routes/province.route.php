<?php

/**
 * Province Routes
 *
 * Endpoints for the signed-in user's own province: the province whose administrator is
 * the current user (set by the super admin under Locations). $action is the path after
 * /province, e.g. "districts" or "districts/5".
 *
 *   GET    info                                        the province + headline numbers
 *   GET    activity?limit=                             audit trail for its administrators
 *   GET    breakdown                                   per-district rollup for reports
 *   GET    districts?search=&status=&limit=&offset=
 *   POST   districts
 *   GET    districts/{id}
 *   PUT    districts/{id}
 *   DELETE districts/{id}                              (refused while it has branches)
 *   GET    branches?district_id=&search=&status=&limit=&offset=          (read-only)
 *   GET    members?district_id=&branch_id=&role=&status=&search=&limit=&offset=   (read-only)
 */

require_once __DIR__ . '/../Controller/ProvinceController.php';
require_once __DIR__ . '/../Middleware/SuperAdminMiddleware.php';

use Ordinatrack\Api\Controller\ProvinceController;
use Ordinatrack\Api\Middleware\SuperAdminMiddleware;

/**
 * Route handler for province endpoints
 *
 * @param string $action The path after /province
 * @param array $data The request data (query string for GET/DELETE, body otherwise)
 * @return string JSON response
 */
function provinceRoutes($action, $data)
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
        ['GET',    '#^info$#',            'getProvince'],
        ['GET',    '#^activity$#',        'getActivity'],
        ['GET',    '#^breakdown$#',       'getBreakdown'],
        ['GET',    '#^districts$#',       'getDistricts'],
        ['POST',   '#^districts$#',       'createDistrict'],
        ['GET',    '#^districts/(\d+)$#', 'getDistrict'],
        ['PUT',    '#^districts/(\d+)$#', 'updateDistrict'],
        ['DELETE', '#^districts/(\d+)$#', 'deleteDistrict'],
        ['GET',    '#^branches$#',        'getBranches'],
        ['GET',    '#^members$#',         'getMembers'],
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
            ? ProvinceController::$handler($user, $data)
            : ProvinceController::$handler($user, $data, $id);
        break;
    }

    if ($response === null) {
        $response = $pathMatched
            ? ['success' => false, 'message' => "Method $method not allowed", 'status' => 405]
            : ['success' => false, 'message' => 'Route not found', 'action' => $action, 'status' => 404];
    }

    // Audit trail (the app's audit logger lives in SuperAdminMiddleware): record
    // successful district changes only, since reads would drown out real activity
    if ($method !== 'GET' && !empty($response['success'])) {
        $logged = $data;
        unset($logged['token']);

        SuperAdminMiddleware::logAccess(
            $user,
            ['POST' => 'CREATE', 'PUT' => 'UPDATE', 'DELETE' => 'DELETE'][$method] ?? $method,
            'districts',
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
