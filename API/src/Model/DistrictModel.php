<?php

namespace Ordinatrack\Api\Model;

use RuntimeException;

/**
 * DistrictModel
 *
 * The district a user runs (districts.administrator_id, assigned by the super admin
 * under Locations), plus the branches and members underneath it.
 *
 * Branch rows are created and edited through LocationModel, so a district lead and the
 * super admin enforce the same rules; this model only makes sure a branch belongs to the
 * lead's own district first. Members are read-only here: they are added by the branch
 * administrator through the branch dashboard.
 *
 * Business-rule failures throw RuntimeException with an HTTP status as the code
 * (404 not found, 409 conflict), like LocationModel.
 */
class DistrictModel
{
    use QueryHelpers;

    /** Branch columns returned to the district dashboard */
    private const BRANCH_COLUMNS = "
        b.id, b.name, b.code, b.description, b.address, b.contact_person, b.contact_phone,
        b.contact_email, b.is_active, b.created_at, b.administrator_id,
        CONCAT(u.first_name, ' ', u.last_name) AS administrator_name,
        (SELECT COUNT(*) FROM members m
            WHERE m.organization_id = b.organization_id AND m.deleted_at IS NULL) AS member_count";

    private const BRANCH_JOIN = 'LEFT JOIN users u ON u.id = b.administrator_id AND u.deleted_at IS NULL';

    /**
     * The district a user administers, or null when they don't run one
     */
    public static function districtFor(int $userId): ?array
    {
        return self::row(
            "SELECT d.id, d.name, d.code, d.description, d.province_id, p.name AS province_name
             FROM districts d
             INNER JOIN provinces p ON p.id = d.province_id
             WHERE d.administrator_id = ? AND d.deleted_at IS NULL
             ORDER BY d.id
             LIMIT 1",
            [$userId]
        );
    }

    /**
     * Headline numbers for the district overview
     */
    public static function stats(int $districtId): array
    {
        $branches = self::row(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(is_active = 1), 0) AS active,
                    COALESCE(SUM(administrator_id IS NOT NULL), 0) AS with_admin
             FROM branches
             WHERE district_id = ? AND deleted_at IS NULL",
            [$districtId]
        );

        $members = self::row(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(m.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')), 0) AS new_this_month
             FROM members m
             INNER JOIN branches b ON b.organization_id = m.organization_id
             WHERE m.deleted_at IS NULL AND b.district_id = ? AND b.deleted_at IS NULL",
            [$districtId]
        );

        return [
            'branches' => [
                'total' => (int)$branches['total'],
                'active' => (int)$branches['active'],
                'with_admin' => (int)$branches['with_admin'],
            ],
            'members' => [
                'total' => (int)$members['total'],
                'new_this_month' => (int)$members['new_this_month'],
            ],
        ];
    }

    /**
     * One page of the district's branches
     *
     * @param array $filters search?, status? (active|inactive)
     * @return array ['branches' => array, 'total' => int]
     */
    public static function getBranches(int $districtId, array $filters, int $limit, int $offset): array
    {
        $where = ['b.district_id = ?', 'b.deleted_at IS NULL'];
        $params = [$districtId];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $where[] = '(b.name LIKE ? OR b.code LIKE ? OR b.contact_person LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        $status = $filters['status'] ?? '';
        if ($status === 'active' || $status === 'inactive') {
            $where[] = 'b.is_active = ?';
            $params[] = $status === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $where);
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        // LIMIT/OFFSET are clamped ints, safe to inline (native prepares reject string LIMIT params)
        $branches = self::rows(
            "SELECT " . self::BRANCH_COLUMNS . "
             FROM branches b " . self::BRANCH_JOIN . "
             WHERE $whereSql
             ORDER BY b.name
             LIMIT $limit OFFSET $offset",
            $params
        );
        $total = (int)(self::row("SELECT COUNT(*) AS count FROM branches b WHERE $whereSql", $params)['count'] ?? 0);

        return ['branches' => array_map([self::class, 'formatBranch'], $branches), 'total' => $total];
    }

    /**
     * One branch, but only when it belongs to this district
     */
    public static function getBranch(int $districtId, int $id): ?array
    {
        $branch = self::row(
            "SELECT " . self::BRANCH_COLUMNS . "
             FROM branches b " . self::BRANCH_JOIN . "
             WHERE b.id = ? AND b.district_id = ? AND b.deleted_at IS NULL",
            [$id, $districtId]
        );

        return $branch ? self::formatBranch($branch) : null;
    }

    /**
     * Delete one of the district's branches. Refused while it still has members, so
     * member records can never be orphaned by removing their branch.
     */
    public static function deleteBranch(int $districtId, int $id): void
    {
        self::requireBranch($districtId, $id);
        LocationModel::delete('branches', $id);
    }

    public static function requireBranch(int $districtId, int $id): array
    {
        $branch = self::getBranch($districtId, $id);
        if (!$branch) {
            throw new RuntimeException('Branch not found in your district', 404);
        }
        return $branch;
    }

    /**
     * One page of members across every branch in the district
     *
     * @param array $filters search?, status? (active|inactive), role?, branch_id?
     * @return array ['members' => array, 'total' => int]
     */
    public static function getMembers(int $districtId, array $filters, int $limit, int $offset): array
    {
        $where = ['m.deleted_at IS NULL', 'b.district_id = ?', 'b.deleted_at IS NULL'];
        $params = [$districtId];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $where[] = "(CONCAT(m.first_name, ' ', m.last_name) LIKE ? OR m.email LIKE ? OR m.phone LIKE ?)";
            array_push($params, $like, $like, $like);
        }

        $status = $filters['status'] ?? '';
        if ($status === 'active' || $status === 'inactive') {
            $where[] = 'm.is_active = ?';
            $params[] = $status === 'active' ? 1 : 0;
        }

        if (!empty($filters['branch_id'])) {
            $where[] = 'b.id = ?';
            $params[] = (int)$filters['branch_id'];
        }

        if (!empty($filters['role'])) {
            $where[] = 'EXISTS (SELECT 1 FROM member_roles r
                WHERE r.member_id = m.id AND r.role_name = ? AND r.is_active = 1 AND r.deleted_at IS NULL)';
            $params[] = (string)$filters['role'];
        }

        $whereSql = implode(' AND ', $where);
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $members = self::rows(
            "SELECT m.id, m.first_name, m.last_name, m.email, m.phone, m.member_since, m.is_active, m.created_at,
                    b.id AS branch_id, b.name AS branch_name,
                    (SELECT r.role_name FROM member_roles r
                        WHERE r.member_id = m.id AND r.is_active = 1 AND r.deleted_at IS NULL
                        ORDER BY r.id DESC LIMIT 1) AS role
             FROM members m
             INNER JOIN branches b ON b.organization_id = m.organization_id
             WHERE $whereSql
             ORDER BY m.first_name, m.last_name, m.id
             LIMIT $limit OFFSET $offset",
            $params
        );
        $total = (int)(self::row(
            "SELECT COUNT(*) AS count FROM members m
             INNER JOIN branches b ON b.organization_id = m.organization_id
             WHERE $whereSql",
            $params
        )['count'] ?? 0);

        return ['members' => array_map([self::class, 'formatMember'], $members), 'total' => $total];
    }

    /**
     * Recent audit entries for the people who run this district and its branches
     */
    public static function getActivity(int $districtId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        return self::rows(
            "SELECT al.id, al.action, al.resource_type, al.resource_id, al.new_values, al.created_at,
                    u.first_name, u.last_name, u.email
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.user_id IN (
                 SELECT d.administrator_id FROM districts d
                 WHERE d.id = ? AND d.administrator_id IS NOT NULL
                 UNION
                 SELECT b.administrator_id FROM branches b
                 WHERE b.district_id = ? AND b.administrator_id IS NOT NULL AND b.deleted_at IS NULL
             )
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT $limit",
            [$districtId, $districtId]
        );
    }

    // ==================== HELPERS ====================

    private static function formatBranch(array $branch): array
    {
        $branch['id'] = (int)$branch['id'];
        $branch['is_active'] = (bool)$branch['is_active'];
        $branch['member_count'] = (int)$branch['member_count'];
        $branch['administrator_id'] = $branch['administrator_id'] ? (int)$branch['administrator_id'] : null;
        return $branch;
    }

    private static function formatMember(array $member): array
    {
        $member['id'] = (int)$member['id'];
        $member['branch_id'] = (int)$member['branch_id'];
        $member['is_active'] = (bool)$member['is_active'];
        $member['role'] = $member['role'] ?? MemberModel::ROLES[0];
        return $member;
    }
}
