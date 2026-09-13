<?php

namespace Ordinatrack\Api\Controller;

use Ordinatrack\Api\Model\DashboardModel;

/**
 * Dashboard Controller
 * 
 * Provides hierarchical statistics and performance metrics endpoints
 * for province, district, and branch performance analysis with drill-down capability.
 */
class DashboardController
{
    /**
     * GET /dashboard/provinces - Get all provinces with performance stats
     * 
     * Query params:
     * - limit: Number of results (default 50)
     * - offset: Pagination offset (default 0)
     * - sort: Sort field (default by performance)
     * 
     * @param object $user Authenticated user
     * @param string $method HTTP method
     * @param object $body Request body
     * @return array Response with provinces and stats
     */
    public static function getProvinces($user, $method, $body): array
    {
        try {
            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            // Validate pagination
            $limit = min($limit, 100);
            $offset = max($offset, 0);

            $provinces = DashboardModel::getProvinceStats($limit, $offset);

            return [
                'success' => true,
                'data' => $provinces,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($provinces)
                ]
            ];
        } catch (\Exception $e) {
            error_log("Error in getProvinces: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch provinces',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * GET /dashboard/districts/:id - Get districts for a province
     * 
     * Query params:
     * - limit: Number of results (default 50)
     * - offset: Pagination offset (default 0)
     * 
     * @param object $user Authenticated user
     * @param string $method HTTP method
     * @param object $body Request body
     * @param int $provinceId Province ID
     * @return array Response with districts and stats
     */
    public static function getDistrictsByProvince($user, $method, $body, $provinceId): array
    {
        try {
            $provinceId = (int)$provinceId;
            
            if ($provinceId <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid province ID',
                    'statuscode' => 400
                ];
            }

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            // Validate pagination
            $limit = min($limit, 100);
            $offset = max($offset, 0);

            $districts = DashboardModel::getDistrictStats($provinceId, $limit, $offset);

            if (empty($districts)) {
                return [
                    'success' => true,
                    'data' => [],
                    'message' => 'No districts found for this province',
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'count' => 0
                    ]
                ];
            }

            return [
                'success' => true,
                'data' => $districts,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($districts)
                ]
            ];
        } catch (\Exception $e) {
            error_log("Error in getDistrictsByProvince: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch districts',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * GET /dashboard/branches/:id - Get branches for a district
     * 
     * Query params:
     * - limit: Number of results (default 50)
     * - offset: Pagination offset (default 0)
     * 
     * @param object $user Authenticated user
     * @param string $method HTTP method
     * @param object $body Request body
     * @param int $districtId District ID
     * @return array Response with branches and stats
     */
    public static function getBranchesByDistrict($user, $method, $body, $districtId): array
    {
        try {
            $districtId = (int)$districtId;
            
            if ($districtId <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid district ID',
                    'statuscode' => 400
                ];
            }

            $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

            // Validate pagination
            $limit = min($limit, 100);
            $offset = max($offset, 0);

            $branches = DashboardModel::getBranchStats($districtId, $limit, $offset);

            if (empty($branches)) {
                return [
                    'success' => true,
                    'data' => [],
                    'message' => 'No branches found for this district',
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $offset,
                        'count' => 0
                    ]
                ];
            }

            return [
                'success' => true,
                'data' => $branches,
                'pagination' => [
                    'limit' => $limit,
                    'offset' => $offset,
                    'count' => count($branches)
                ]
            ];
        } catch (\Exception $e) {
            error_log("Error in getBranchesByDistrict: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch branches',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * GET /dashboard/province/:id/hierarchy - Get complete province hierarchy
     * 
     * Returns province with all nested districts and branches
     * 
     * @param object $user Authenticated user
     * @param string $method HTTP method
     * @param object $body Request body
     * @param int $provinceId Province ID
     * @return array Response with complete hierarchy
     */
    public static function getProvinceHierarchy($user, $method, $body, $provinceId): array
    {
        try {
            $provinceId = (int)$provinceId;
            
            if ($provinceId <= 0) {
                return [
                    'success' => false,
                    'message' => 'Invalid province ID',
                    'statuscode' => 400
                ];
            }

            $result = DashboardModel::getProvinceHierarchy($provinceId);

            return $result;
        } catch (\Exception $e) {
            error_log("Error in getProvinceHierarchy: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch province hierarchy',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * GET /dashboard/stats - Get overall system statistics
     * 
     * @param object $user Authenticated user
     * @param string $method HTTP method
     * @param object $body Request body
     * @return array Response with overall stats
     */
    public static function getOverallStats($user, $method, $body): array
    {
        try {
            $stats = DashboardModel::getOverallStats();

            return [
                'success' => true,
                'data' => $stats
            ];
        } catch (\Exception $e) {
            error_log("Error in getOverallStats: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch overall stats',
                'error' => $e->getMessage()
            ];
        }
    }
}
