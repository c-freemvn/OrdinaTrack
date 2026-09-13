<?php

namespace Ordinatrack\Api\Model;

use Ordinatrack\Api\Connections\Database;
use Exception;

/**
 * Dashboard Model
 * 
 * Provides hierarchical statistics and performance metrics for:
 * - Province-level overview and rankings
 * - District-level breakdown within provinces
 * - Branch-level metrics within districts
 * 
 * Performance metrics include:
 * - Active members count
 * - Branch/district count
 * - Activity trends
 * - Growth metrics
 */
class DashboardModel
{
    /**
     * Get province-level statistics sorted by performance
     * 
     * Performance ranking based on:
     * - Number of active members
     * - Number of active branches
     * - Recent activity
     * 
     * @param int $limit Maximum provinces to return
     * @param int $offset Pagination offset
     * @return array Provinces with stats sorted by performance (best to worst)
     */
    public static function getProvinceStats(int $limit = 50, int $offset = 0): array
    {
        try {
            $query = "
                SELECT 
                    p.id,
                    p.name,
                    p.code,
                    COUNT(DISTINCT d.id) as district_count,
                    COUNT(DISTINCT b.id) as branch_count,
                    COUNT(DISTINCT CASE WHEN m.is_active = true THEN m.id END) as active_members,
                    COUNT(DISTINCT u.id) as active_users,
                    p.created_at,
                    YEAR(p.created_at) as year_established,
                    DATEDIFF(NOW(), MAX(al.created_at)) as days_since_activity
                FROM provinces p
                LEFT JOIN districts d ON d.province_id = p.id AND d.deleted_at IS NULL
                LEFT JOIN branches b ON b.district_id = d.id AND b.deleted_at IS NULL
                LEFT JOIN members m ON m.organization_id IN (
                    SELECT organization_id FROM provinces WHERE id = p.id
                ) AND m.deleted_at IS NULL
                LEFT JOIN users u ON u.is_active = true AND u.deleted_at IS NULL
                LEFT JOIN audit_logs al ON al.resource_type = 'province' AND al.resource_id = p.id
                WHERE p.deleted_at IS NULL
                GROUP BY p.id, p.name, p.code, p.created_at
                ORDER BY active_members DESC, branch_count DESC, district_count DESC
                LIMIT ? OFFSET ?
            ";

            $provinces = Database::fetchAll($query, [$limit, $offset]);

            // Add performance score and trend
            foreach ($provinces as &$province) {
                $province['performance_score'] = self::calculatePerformanceScore(
                    $province['active_members'] ?? 0,
                    $province['branch_count'] ?? 0,
                    $province['district_count'] ?? 0
                );
                
                $province['trend'] = self::getProvinceTrend($province['id']);
                $province['status'] = self::getLocationStatus($province['days_since_activity'] ?? null);
            }

            return $provinces;
        } catch (Exception $e) {
            error_log("Error fetching province stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get district-level statistics for a specific province
     * 
     * @param int $provinceId Province ID
     * @param int $limit Maximum districts to return
     * @param int $offset Pagination offset
     * @return array Districts with stats sorted by performance
     */
    public static function getDistrictStats(int $provinceId, int $limit = 50, int $offset = 0): array
    {
        try {
            $query = "
                SELECT 
                    d.id,
                    d.name,
                    d.code,
                    d.province_id,
                    p.name as province_name,
                    COUNT(DISTINCT b.id) as branch_count,
                    COUNT(DISTINCT CASE WHEN m.is_active = true THEN m.id END) as active_members,
                    COUNT(DISTINCT u.id) as active_users,
                    d.created_at,
                    DATEDIFF(NOW(), MAX(al.created_at)) as days_since_activity
                FROM districts d
                LEFT JOIN provinces p ON p.id = d.province_id
                LEFT JOIN branches b ON b.district_id = d.id AND b.deleted_at IS NULL
                LEFT JOIN members m ON m.organization_id IN (
                    SELECT organization_id FROM districts WHERE id = d.id
                ) AND m.deleted_at IS NULL
                LEFT JOIN users u ON u.is_active = true AND u.deleted_at IS NULL
                LEFT JOIN audit_logs al ON al.resource_type = 'district' AND al.resource_id = d.id
                WHERE d.province_id = ? AND d.deleted_at IS NULL
                GROUP BY d.id, d.name, d.code, d.province_id, p.name, d.created_at
                ORDER BY active_members DESC, branch_count DESC
                LIMIT ? OFFSET ?
            ";

            $districts = Database::fetchAll($query, [$provinceId, $limit, $offset]);

            // Add performance data
            foreach ($districts as &$district) {
                $district['performance_score'] = self::calculatePerformanceScore(
                    $district['active_members'] ?? 0,
                    $district['branch_count'] ?? 0,
                    0
                );
                
                $district['trend'] = self::getDistrictTrend($district['id']);
                $district['status'] = self::getLocationStatus($district['days_since_activity'] ?? null);
            }

            return $districts;
        } catch (Exception $e) {
            error_log("Error fetching district stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get branch-level statistics for a specific district
     * 
     * @param int $districtId District ID
     * @param int $limit Maximum branches to return
     * @param int $offset Pagination offset
     * @return array Branches with stats sorted by performance
     */
    public static function getBranchStats(int $districtId, int $limit = 50, int $offset = 0): array
    {
        try {
            $query = "
                SELECT 
                    b.id,
                    b.name,
                    b.code,
                    b.district_id,
                    d.name as district_name,
                    p.name as province_name,
                    p.id as province_id,
                    d.id as district_id_full,
                    COUNT(DISTINCT CASE WHEN m.is_active = true THEN m.id END) as active_members,
                    COUNT(DISTINCT u.id) as active_users,
                    b.contact_person,
                    b.contact_phone,
                    b.contact_email,
                    b.address,
                    b.created_at,
                    DATEDIFF(NOW(), MAX(al.created_at)) as days_since_activity
                FROM branches b
                LEFT JOIN districts d ON d.id = b.district_id
                LEFT JOIN provinces p ON p.id = d.province_id
                LEFT JOIN members m ON m.organization_id = b.organization_id AND m.deleted_at IS NULL
                LEFT JOIN users u ON u.is_active = true AND u.deleted_at IS NULL
                LEFT JOIN audit_logs al ON al.resource_type = 'branch' AND al.resource_id = b.id
                WHERE b.district_id = ? AND b.deleted_at IS NULL
                GROUP BY b.id, b.name, b.code, b.district_id, d.name, p.name, p.id, d.id, 
                         b.contact_person, b.contact_phone, b.contact_email, b.address, b.created_at
                ORDER BY active_members DESC
                LIMIT ? OFFSET ?
            ";

            $branches = Database::fetchAll($query, [$districtId, $limit, $offset]);

            // Add performance data
            foreach ($branches as &$branch) {
                $branch['performance_score'] = self::calculatePerformanceScore(
                    $branch['active_members'] ?? 0,
                    0,
                    0
                );
                
                $branch['trend'] = self::getBranchTrend($branch['id']);
                $branch['status'] = self::getLocationStatus($branch['days_since_activity'] ?? null);
            }

            return $branches;
        } catch (Exception $e) {
            error_log("Error fetching branch stats: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get comprehensive province overview with nested districts and branches
     * 
     * @param int $provinceId Province ID
     * @return array Complete hierarchy with all stats
     */
    public static function getProvinceHierarchy(int $provinceId): array
    {
        try {
            // Get province stats
            $provinceQuery = "
                SELECT 
                    p.id,
                    p.name,
                    p.code,
                    COUNT(DISTINCT d.id) as district_count,
                    COUNT(DISTINCT b.id) as branch_count,
                    COUNT(DISTINCT CASE WHEN m.is_active = true THEN m.id END) as active_members,
                    p.created_at
                FROM provinces p
                LEFT JOIN districts d ON d.province_id = p.id AND d.deleted_at IS NULL
                LEFT JOIN branches b ON b.district_id = d.id AND b.deleted_at IS NULL
                LEFT JOIN members m ON m.organization_id IN (
                    SELECT organization_id FROM provinces WHERE id = p.id
                )
                WHERE p.id = ? AND p.deleted_at IS NULL
                GROUP BY p.id, p.name, p.code, p.created_at
            ";

            $province = Database::fetch($provinceQuery, [$provinceId]);

            if (!$province) {
                return ['success' => false, 'message' => 'Province not found'];
            }

            // Get districts for this province
            $province['districts'] = self::getDistrictStats($provinceId, 100, 0);

            // Get top branches for each district
            foreach ($province['districts'] as &$district) {
                $district['branches'] = self::getBranchStats($district['id'], 50, 0);
            }

            $province['performance_score'] = self::calculatePerformanceScore(
                $province['active_members'] ?? 0,
                $province['branch_count'] ?? 0,
                $province['district_count'] ?? 0
            );

            return [
                'success' => true,
                'data' => $province
            ];
        } catch (Exception $e) {
            error_log("Error fetching province hierarchy: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to fetch hierarchy'];
        }
    }

    /**
     * Calculate performance score (0-100)
     * 
     * Based on: members (50%), branches (30%), districts (20%)
     */
    private static function calculatePerformanceScore(int $members, int $branches, int $districts): int
    {
        // Normalize scores (assumes max realistic values)
        $memberScore = min(($members / 1000) * 50, 50);      // Max 50 points
        $branchScore = min(($branches / 100) * 30, 30);      // Max 30 points
        $districtScore = min(($districts / 50) * 20, 20);    // Max 20 points

        return (int)round($memberScore + $branchScore + $districtScore);
    }

    /**
     * Get province trend data (last 30 days)
     */
    private static function getProvinceTrend(int $provinceId): array
    {
        try {
            $query = "
                SELECT 
                    DATE(al.created_at) as date,
                    COUNT(DISTINCT CASE WHEN al.action = 'CREATE' THEN al.id END) as new_activities,
                    COUNT(DISTINCT CASE WHEN al.action = 'UPDATE' THEN al.id END) as updates
                FROM audit_logs al
                WHERE al.resource_type = 'province' AND al.resource_id = ?
                AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(al.created_at)
                ORDER BY date DESC
                LIMIT 30
            ";

            return Database::fetchAll($query, [$provinceId]) ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get district trend data (last 30 days)
     */
    private static function getDistrictTrend(int $districtId): array
    {
        try {
            $query = "
                SELECT 
                    DATE(al.created_at) as date,
                    COUNT(DISTINCT CASE WHEN al.action = 'CREATE' THEN al.id END) as new_activities,
                    COUNT(DISTINCT CASE WHEN al.action = 'UPDATE' THEN al.id END) as updates
                FROM audit_logs al
                WHERE al.resource_type = 'district' AND al.resource_id = ?
                AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(al.created_at)
                ORDER BY date DESC
                LIMIT 30
            ";

            return Database::fetchAll($query, [$districtId]) ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get branch trend data (last 30 days)
     */
    private static function getBranchTrend(int $branchId): array
    {
        try {
            $query = "
                SELECT 
                    DATE(al.created_at) as date,
                    COUNT(DISTINCT CASE WHEN al.action = 'CREATE' THEN al.id END) as new_activities,
                    COUNT(DISTINCT CASE WHEN al.action = 'UPDATE' THEN al.id END) as updates
                FROM audit_logs al
                WHERE al.resource_type = 'branch' AND al.resource_id = ?
                AND al.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY DATE(al.created_at)
                ORDER BY date DESC
                LIMIT 30
            ";

            return Database::fetchAll($query, [$branchId]) ?? [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Determine location status based on activity recency
     */
    private static function getLocationStatus(?int $daysSinceActivity): string
    {
        if ($daysSinceActivity === null) {
            return 'no_data';
        }

        if ($daysSinceActivity <= 7) {
            return 'active';
        } elseif ($daysSinceActivity <= 30) {
            return 'moderate';
        } elseif ($daysSinceActivity <= 90) {
            return 'inactive';
        } else {
            return 'dormant';
        }
    }

    /**
     * Get overall statistics summary
     */
    public static function getOverallStats(): array
    {
        try {
            $stats = [
                'total_provinces' => Database::fetch(
                    "SELECT COUNT(*) as count FROM provinces WHERE deleted_at IS NULL"
                )['count'] ?? 0,
                
                'total_districts' => Database::fetch(
                    "SELECT COUNT(*) as count FROM districts WHERE deleted_at IS NULL"
                )['count'] ?? 0,
                
                'total_branches' => Database::fetch(
                    "SELECT COUNT(*) as count FROM branches WHERE deleted_at IS NULL"
                )['count'] ?? 0,
                
                'total_members' => Database::fetch(
                    "SELECT COUNT(*) as count FROM members WHERE deleted_at IS NULL AND is_active = true"
                )['count'] ?? 0,
                
                'total_users' => Database::fetch(
                    "SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL AND is_active = true"
                )['count'] ?? 0,
                
                'active_locations' => Database::fetch(
                    "SELECT COUNT(*) as count FROM audit_logs 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                     AND resource_type IN ('province', 'district', 'branch')"
                )['count'] ?? 0
            ];

            return $stats;
        } catch (Exception $e) {
            error_log("Error fetching overall stats: " . $e->getMessage());
            return [];
        }
    }
}
