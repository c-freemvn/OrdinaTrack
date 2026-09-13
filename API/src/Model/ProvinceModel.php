<?php

namespace Ordinatrack\Api\Model;

/**
 * ProvinceModel
 *
 * The province a user runs (provinces.administrator_id, assigned by the super admin
 * under Locations), plus the districts, branches and members underneath it.
 *
 * District rows are created and edited through LocationModel, so a province lead and the
 * super admin enforce the same rules; this model only makes sure a district belongs to
 * the lead's own province first. Branches and members are read-only here: they belong to
 * the district lead and the branch administrator respectively.
 *
 * Business-rule failures throw RuntimeException with an HTTP status as the code
 * (404 not found, 409 conflict), like LocationModel.
 */
class ProvinceModel
{
    use QueryHelpers;

    /** District columns returned to the province dashboard */
    private const DISTRICT_COLUMNS = "
        d.id, d.name, d.code, d.description, d.is_active, d.created_at, d.administrator_id,
        CONCAT(u.first_name, ' ', u.last_name) AS administrator_name,
        (SELECT COUNT(*) FROM branches b
            WHERE b.district_id = d.id AND b.deleted_at IS NULL) AS branch_count,
        (SELECT COUNT(*) FROM members m
            INNER JOIN branches b ON b.organization_id = m.organization_id
            WHERE b.district_id = d.id AND b.deleted_at IS NULL AND m.deleted_at IS NULL) AS member_count";

    private const DISTRICT_JOIN = 'LEFT JOIN users u ON u.id = d.administrator_id AND u.deleted_at IS NULL';

    /**
     * The province a user administers, or null when they don't run one
     */
    public static function provinceFor(int $userId): ?array
    {
        return self::row(
            "SELECT p.id, p.name, p.code, p.description
             FROM provinces p
             WHERE p.administrator_id = ? AND p.deleted_at IS NULL
             ORDER BY p.id
             LIMIT 1",
            [$userId]
        );
    }

    /**
     * Headline numbers for the province overview
     */
    public static function stats(int $provinceId): array
    {
        $districts = self::row(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(is_active = 1), 0) AS active,
                    COALESCE(SUM(administrator_id IS NOT NULL), 0) AS with_admin
             FROM districts
             WHERE province_id = ? AND deleted_at IS NULL",
            [$provinceId]
        );

        $branches = self::row(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(b.is_active = 1), 0) AS active,
                    COALESCE(SUM(b.administrator_id IS NOT NULL), 0) AS with_admin
             FROM branches b
             INNER JOIN districts d ON d.id = b.district_id
             WHERE d.province_id = ? AND b.deleted_at IS NULL AND d.deleted_at IS NULL",
            [$provinceId]
        );

        $members = self::row(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(m.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')), 0) AS new_this_month
             FROM members m
             INNER JOIN branches b ON b.organization_id = m.organization_id
             INNER JOIN districts d ON d.id = b.district_id
             WHERE d.province_id = ? AND m.deleted_at IS NULL AND b.deleted_at IS NULL AND d.deleted_at IS NULL",
            [$provinceId]
        );

        return [
            'districts' => [
                'total' => (int)$districts['total'],
                'active' => (int)$districts['active'],
                'with_admin' => (int)$districts['with_admin'],
            ],
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
     * One page of the province's districts
     *
     * @param array $filters search?, status? (active|inactive)
     * @return array ['districts' => array, 'total' => int]
     */
    public static function getDistricts(int $provinceId, array $filters, int $limit, int $offset): array
    {
        $where = ['d.province_id = ?', 'd.deleted_at IS NULL'];
        $params = [$provinceId];

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $where[] = '(d.name LIKE ? OR d.code LIKE ?)';
            array_push($params, $like, $like);
        }

        $status = $filters['status'] ?? '';
        if ($status === 'active' || $status === 'inactive') {
            $where[] = 'd.is_active = ?';
            $params[] = $status === 'active' ? 1 : 0;
        }

        $whereSql = implode(' AND ', $where);
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        // LIMIT/OFFSET are clamped ints, safe to inline (native prepares reject string LIMIT params)
        $districts = self::rows(
            "SELECT " . self::DISTRICT_COLUMNS . "
             FROM districts d " . self::DISTRICT_JOIN . "
             WHERE $whereSql
             ORDER BY d.name
             LIMIT $limit OFFSET $offset",
            $params
        );
        $total = (int)(self::row("SELECT COUNT(*) AS count FROM districts d WHERE $whereSql", $params)['count'] ?? 0);

        return ['districts' => array_map([self::class, 'formatDistrict'], $districts), 'total' => $total];
    }

    /**
     * One district, but only when it belongs to this province
     */
    public static function getDistrict(int $provinceId, int $id): ?array
    {
        $district = self::row(
            "SELECT " . self::DISTRICT_COLUMNS . "
             FROM districts d " . self::DISTRICT_JOIN . "
             WHERE d.id = ? AND d.province_id = ? AND d.deleted_at IS NULL",
            [$id, $provinceId]
        );

        return $district ? self::formatDistrict($district) : null;
    }

    public static function requireDistrict(int $provinceId, int $id): array
    {
        $district = self::getDistrict($provinceId, $id);
        if (!$district) {
            throw new \RuntimeException('District not found in your province', 404);
        }
        return $district;
    }

    /**
     * Delete one of the province's districts (LocationModel refuses while it has branches)
     */
    public static function deleteDistrict(int $provinceId, int $id): void
    {
        self::requireDistrict($provinceId, $id);
        LocationModel::delete('districts', $id);
    }

    /**
     * One page of branches across the province (read-only)
     *
     * @param array $filters search?, status?, district_id?
     * @return array ['branches' => array, 'total' => int]
     */
    public static function getBranches(int $provinceId, array $filters, int $limit, int $offset): array
    {
        $where = ['d.province_id = ?', 'b.deleted_at IS NULL', 'd.deleted_at IS NULL'];
        $params = [$provinceId];

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

        if (!empty($filters['district_id'])) {
            $where[] = 'b.district_id = ?';
            $params[] = (int)$filters['district_id'];
        }

        $whereSql = implode(' AND ', $where);
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $branches = self::rows(
            "SELECT b.id, b.name, b.code, b.address, b.contact_person, b.contact_phone, b.contact_email,
                    b.is_active, b.created_at, b.district_id, d.name AS district_name,
                    CONCAT(u.first_name, ' ', u.last_name) AS administrator_name,
                    (SELECT COUNT(*) FROM members m
                        WHERE m.organization_id = b.organization_id AND m.deleted_at IS NULL) AS member_count
             FROM branches b
             INNER JOIN districts d ON d.id = b.district_id
             LEFT JOIN users u ON u.id = b.administrator_id AND u.deleted_at IS NULL
             WHERE $whereSql
             ORDER BY d.name, b.name
             LIMIT $limit OFFSET $offset",
            $params
        );
        $total = (int)(self::row(
            "SELECT COUNT(*) AS count FROM branches b
             INNER JOIN districts d ON d.id = b.district_id
             WHERE $whereSql",
            $params
        )['count'] ?? 0);

        return ['branches' => array_map([self::class, 'formatBranch'], $branches), 'total' => $total];
    }

    /**
     * One page of members across the whole province (read-only)
     *
     * @param array $filters search?, status?, role?, district_id?, branch_id?
     * @return array ['members' => array, 'total' => int]
     */
    public static function getMembers(int $provinceId, array $filters, int $limit, int $offset): array
    {
        $where = ['d.province_id = ?', 'm.deleted_at IS NULL', 'b.deleted_at IS NULL', 'd.deleted_at IS NULL'];
        $params = [$provinceId];

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

        if (!empty($filters['district_id'])) {
            $where[] = 'd.id = ?';
            $params[] = (int)$filters['district_id'];
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
                    b.id AS branch_id, b.name AS branch_name, d.id AS district_id, d.name AS district_name,
                    (SELECT r.role_name FROM member_roles r
                        WHERE r.member_id = m.id AND r.is_active = 1 AND r.deleted_at IS NULL
                        ORDER BY r.id DESC LIMIT 1) AS role
             FROM members m
             INNER JOIN branches b ON b.organization_id = m.organization_id
             INNER JOIN districts d ON d.id = b.district_id
             WHERE $whereSql
             ORDER BY m.first_name, m.last_name, m.id
             LIMIT $limit OFFSET $offset",
            $params
        );
        $total = (int)(self::row(
            "SELECT COUNT(*) AS count FROM members m
             INNER JOIN branches b ON b.organization_id = m.organization_id
             INNER JOIN districts d ON d.id = b.district_id
             WHERE $whereSql",
            $params
        )['count'] ?? 0);

        return ['members' => array_map([self::class, 'formatMember'], $members), 'total' => $total];
    }

    /**
     * Per-district rollup for the reports page, biggest membership first
     */
    public static function breakdown(int $provinceId): array
    {
        $rows = self::rows(
            "SELECT " . self::DISTRICT_COLUMNS . ",
                    (SELECT COUNT(*) FROM members m
                        INNER JOIN branches b ON b.organization_id = m.organization_id
                        WHERE b.district_id = d.id AND b.deleted_at IS NULL AND m.deleted_at IS NULL
                          AND m.created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS new_members
             FROM districts d " . self::DISTRICT_JOIN . "
             WHERE d.province_id = ? AND d.deleted_at IS NULL
             ORDER BY member_count DESC, d.name",
            [$provinceId]
        );

        return array_map(function (array $row) {
            $row = self::formatDistrict($row);
            $row['new_members'] = (int)$row['new_members'];
            return $row;
        }, $rows);
    }

    /**
     * Recent audit entries for everyone who runs this province, its districts and branches
     */
    public static function getActivity(int $provinceId, int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));

        return self::rows(
            "SELECT al.id, al.action, al.resource_type, al.resource_id, al.new_values, al.created_at,
                    u.first_name, u.last_name, u.email
             FROM audit_logs al
             LEFT JOIN users u ON u.id = al.user_id
             WHERE al.user_id IN (
                 SELECT p.administrator_id FROM provinces p
                 WHERE p.id = ? AND p.administrator_id IS NOT NULL
                 UNION
                 SELECT d.administrator_id FROM districts d
                 WHERE d.province_id = ? AND d.administrator_id IS NOT NULL AND d.deleted_at IS NULL
                 UNION
                 SELECT b.administrator_id FROM branches b
                 INNER JOIN districts d2 ON d2.id = b.district_id
                 WHERE d2.province_id = ? AND b.administrator_id IS NOT NULL
                   AND b.deleted_at IS NULL AND d2.deleted_at IS NULL
             )
             ORDER BY al.created_at DESC, al.id DESC
             LIMIT $limit",
            [$provinceId, $provinceId, $provinceId]
        );
    }

    // ==================== HELPERS ====================

    private static function formatDistrict(array $district): array
    {
        $district['id'] = (int)$district['id'];
        $district['is_active'] = (bool)$district['is_active'];
        $district['branch_count'] = (int)$district['branch_count'];
        $district['member_count'] = (int)$district['member_count'];
        $district['administrator_id'] = $district['administrator_id'] ? (int)$district['administrator_id'] : null;
        return $district;
    }

    private static function formatBranch(array $branch): array
    {
        $branch['id'] = (int)$branch['id'];
        $branch['district_id'] = (int)$branch['district_id'];
        $branch['is_active'] = (bool)$branch['is_active'];
        $branch['member_count'] = (int)$branch['member_count'];
        return $branch;
    }

    private static function formatMember(array $member): array
    {
        $member['id'] = (int)$member['id'];
        $member['branch_id'] = (int)$member['branch_id'];
        $member['district_id'] = (int)$member['district_id'];
        $member['is_active'] = (bool)$member['is_active'];
        $member['role'] = $member['role'] ?? MemberModel::ROLES[0];
        return $member;
    }
}
