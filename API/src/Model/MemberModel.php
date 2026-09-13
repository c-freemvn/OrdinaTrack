<?php

namespace Ordinatrack\Api\Model;

use Ordinatrack\Api\Connections\Database;
use RuntimeException;

/**
 * MemberModel
 *
 * Members of a branch. A user runs a branch when they are its administrator
 * (branches.administrator_id, assigned by the super admin under Locations).
 *
 * Members belong to the branch's organization record (members.organization_id), which
 * is created when the branch's first member is added. Each member holds one active role
 * in member_roles; changing it ends the old role so the history is kept. Removing a
 * member is a soft delete (deleted_at).
 *
 * Business-rule failures throw RuntimeException with an HTTP status as the code
 * (404 not found), like LocationModel.
 */
class MemberModel
{
    use QueryHelpers;

    /** Roles a member can hold in a branch */
    public const ROLES = ['Member', 'Steward', 'Usher'];

    private const SELECT_COLUMNS = "
        m.id, m.first_name, m.last_name, m.email, m.phone, m.member_since, m.is_active, m.created_at,
        (SELECT r.role_name FROM member_roles r
            WHERE r.member_id = m.id AND r.is_active = 1 AND r.deleted_at IS NULL
            ORDER BY r.id DESC LIMIT 1) AS role";

    /**
     * The branch a user administers, or null when they don't run one
     */
    public static function branchFor(int $userId): ?array
    {
        return self::row(
            "SELECT b.id, b.name, b.organization_id, d.name AS district_name, p.name AS province_name
             FROM branches b
             INNER JOIN districts d ON d.id = b.district_id
             INNER JOIN provinces p ON p.id = d.province_id
             WHERE b.administrator_id = ? AND b.deleted_at IS NULL
             ORDER BY b.id
             LIMIT 1",
            [$userId]
        );
    }

    /**
     * One page of a branch's members, sorted by name
     *
     * @param int|null $organizationId The branch's organization (null until its first member)
     * @param array $filters search?, status? (active|inactive), role?
     * @return array ['members' => array, 'total' => int]
     */
    public static function getAll(?int $organizationId, array $filters, int $limit, int $offset): array
    {
        if (!$organizationId) {
            return ['members' => [], 'total' => 0];
        }

        $where = ['m.organization_id = ?', 'm.deleted_at IS NULL'];
        $params = [$organizationId];

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

        if (!empty($filters['role'])) {
            $where[] = 'EXISTS (SELECT 1 FROM member_roles r
                WHERE r.member_id = m.id AND r.role_name = ? AND r.is_active = 1 AND r.deleted_at IS NULL)';
            $params[] = (string)$filters['role'];
        }

        $whereSql = implode(' AND ', $where);
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        // LIMIT/OFFSET are clamped ints, safe to inline (native prepares reject string LIMIT params)
        $members = self::rows(
            "SELECT " . self::SELECT_COLUMNS . "
             FROM members m
             WHERE $whereSql
             ORDER BY m.first_name, m.last_name, m.id
             LIMIT $limit OFFSET $offset",
            $params
        );
        $total = (int)(self::row("SELECT COUNT(*) AS count FROM members m WHERE $whereSql", $params)['count'] ?? 0);

        return ['members' => array_map(fn($member) => self::format($member), $members), 'total' => $total];
    }

    public static function getOne(int $organizationId, int $id): ?array
    {
        $member = self::row(
            "SELECT " . self::SELECT_COLUMNS . "
             FROM members m
             WHERE m.id = ? AND m.organization_id = ? AND m.deleted_at IS NULL",
            [$id, $organizationId]
        );

        return $member ? self::format($member) : null;
    }

    /**
     * Add a member to a branch
     *
     * @param array $branch Row from branchFor()
     * @param array $values Member columns (first_name, last_name, email, phone, member_since, is_active)
     */
    public static function create(array $branch, int $userId, array $values, string $role): array
    {
        $organizationId = null;
        $memberId = null;

        self::transaction(function () use ($branch, $userId, $values, $role, &$organizationId, &$memberId) {
            $organizationId = self::organizationFor($branch, $userId);

            $values['organization_id'] = $organizationId;
            $values['member_since'] ??= date('Y-m-d');
            $columns = array_keys($values);
            self::run(
                "INSERT INTO members (" . implode(', ', $columns) . ") VALUES (" . self::placeholders($columns) . ")",
                array_values($values)
            );
            $memberId = (int)Database::lastInsertId();

            self::startRole($memberId, $organizationId, $role);
        });

        return self::getOne($organizationId, $memberId);
    }

    /**
     * Update a member (partial update). A role change ends the current role and starts the new one.
     */
    public static function update(int $organizationId, int $id, array $values, ?string $role): array
    {
        $current = self::requireMember($organizationId, $id);

        self::transaction(function () use ($organizationId, $id, $values, $role, $current) {
            if ($values) {
                self::updateFields('members', $id, $values);
            }
            if ($role !== null && $role !== $current['role']) {
                self::endRoles($id);
                self::startRole($id, $organizationId, $role);
            }
        });

        return self::getOne($organizationId, $id);
    }

    /**
     * Remove a member (soft delete, so past records keep their history)
     */
    public static function delete(int $organizationId, int $id): void
    {
        self::requireMember($organizationId, $id);

        self::transaction(function () use ($id) {
            self::run("UPDATE members SET deleted_at = NOW(), is_active = 0 WHERE id = ?", [$id]);
            self::endRoles($id);
        });
    }

    // ==================== HELPERS ====================

    /**
     * The branch's organization id, creating it the first time (members hang off organizations).
     * The branch row is locked so two first adds can't create two organizations.
     */
    private static function organizationFor(array $branch, int $userId): int
    {
        $current = self::row("SELECT organization_id FROM branches WHERE id = ? FOR UPDATE", [$branch['id']]);
        if (!empty($current['organization_id'])) {
            return (int)$current['organization_id'];
        }

        self::run(
            "INSERT INTO organizations (name, organization_type, created_by) VALUES (?, 'Branch', ?)",
            [$branch['name'], $userId]
        );
        $organizationId = (int)Database::lastInsertId();
        self::run("UPDATE branches SET organization_id = ? WHERE id = ?", [$organizationId, $branch['id']]);

        return $organizationId;
    }

    private static function requireMember(int $organizationId, int $id): array
    {
        $member = self::getOne($organizationId, $id);
        if (!$member) {
            throw new RuntimeException('Member not found', 404);
        }
        return $member;
    }

    private static function startRole(int $memberId, int $organizationId, string $role): void
    {
        self::run(
            "INSERT INTO member_roles (member_id, organization_id, role_name, start_date) VALUES (?, ?, ?, CURDATE())",
            [$memberId, $organizationId, $role]
        );
    }

    private static function endRoles(int $memberId): void
    {
        self::run(
            "UPDATE member_roles SET is_active = 0, end_date = CURDATE()
             WHERE member_id = ? AND is_active = 1 AND deleted_at IS NULL",
            [$memberId]
        );
    }

    private static function format(array $member): array
    {
        $member['id'] = (int)$member['id'];
        $member['is_active'] = (bool)$member['is_active'];
        $member['role'] = $member['role'] ?? self::ROLES[0];
        return $member;
    }
}
