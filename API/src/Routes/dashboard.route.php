<?php

/**
 * Dashboard Routes Handler
 * 
 * Provides hierarchical statistics and performance metrics endpoints
 * for viewing province, district, and branch performance with drill-down capability.
 * 
 * Available actions:
 * - provinces: Get all provinces with performance stats
 * - districts/:id: Get districts for a province
 * - branches/:id: Get branches for a district
 * - province/:id/hierarchy: Get complete province hierarchy (nested)
 * - stats: Get overall system statistics
 */

use Ordinatrack\Api\Controller\DashboardController;

/**
 * Routes handler for dashboard endpoints
 * 
 * @param string $action Action to perform
 * @param array $data Request data
 * @return string JSON response
 */
function dashboardRoutes(string $action, array $data): string
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    
    // Get authenticated user from session
    $user = isset($_SESSION['user']) ? (object)$_SESSION['user'] : null;
    
    // Parse request body for POST/PUT/DELETE
    $body = null;
    if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $body = json_decode(file_get_contents('php://input') ?: '{}');
        } else {
            $body = (object)$data;
        }
    }

    // Route handling with parameter extraction
    switch (true) {
        // Get all provinces with stats
        case $action === 'provinces' && $method === 'GET':
            $response = DashboardController::getProvinces($user, $method, $body);
            break;

        // Get districts for a province
        case preg_match('/^districts\/(\d+)$/', $action, $m) && $method === 'GET':
            $response = DashboardController::getDistrictsByProvince($user, $method, $body, $m[1]);
            break;

        // Get branches for a district
        case preg_match('/^branches\/(\d+)$/', $action, $m) && $method === 'GET':
            $response = DashboardController::getBranchesByDistrict($user, $method, $body, $m[1]);
            break;

        // Get complete province hierarchy (nested)
        case preg_match('/^province\/(\d+)\/hierarchy$/', $action, $m) && $method === 'GET':
            $response = DashboardController::getProvinceHierarchy($user, $method, $body, $m[1]);
            break;

        // Get overall stats
        case $action === 'stats' && $method === 'GET':
            $response = DashboardController::getOverallStats($user, $method, $body);
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
    if (isset($response['statuscode']) && is_int($response['statuscode'])) {
        $statusCode = $response['statuscode'];
        unset($response['statuscode']);
    }

    if (isset($response['status']) && is_int($response['status'])) {
        $statusCode = $response['status'];
        unset($response['status']);
    }

    if (!headers_sent()) {
        http_response_code($statusCode);
    }

    return json_encode($response);
}
