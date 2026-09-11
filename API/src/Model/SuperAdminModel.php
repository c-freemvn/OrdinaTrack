<?php

namespace Ordinatrack\Api\Model;

use Ordinatrack\Api\Config\Config;
use Ordinatrack\Api\Connections\Database;
use RuntimeException;

/**
 * SuperAdminModel
 *
 * Handles all Super Admin operations including:
 * - Role management (CRUD + permission assignment)
 * - Permission management (CRUD)
 * - User management (create, edit, roles, status, password, soft delete)
 * - System statistics and audit log reads
 *
 * Business-rule failures throw RuntimeException with an HTTP status as the code
 * (404 not found, 403 protected, 409 conflict, 422 invalid) for the controller to map.
 * Database failures propagate as PDOException.
 */
class SuperAdminModel
{
    use QueryHelpers;

    /** Roles that cannot be deleted (and whose slug cannot change) */
    public const PROTECTED_ROLES = ['super_admin', 'admin', 'nhq_admin'];

    /** Built-in account that can never be deleted, deactivated or demoted */
    public const SYSTEM_ADMIN_EMAIL = 'super.admin@ordinatrack.com';

    /** Safe user columns (never expose password hashes or reset tokens) */
    private const USER_COLUMNS = 'u.id, u.email, u.first_name, u.last_name, u.phone, u.is_active, u.email_verified, u.created_at, u.last_login_at';

    // ==================== ROLES ====================

    /**
     * Get all roles with permission and user counts
     */
    public static function getAllRoles(): array
    {
        $roles = self::rows("
            SELECT
                r.id, r.name, r.slug, r.description, r.created_at,
                (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permission_count,
                (SELECT COUNT(*) FROM user_roles ur
                    INNER JOIN users u ON u.id = ur.user_id AND u.deleted_at IS NULL
                    WHERE ur.role_id = r.id) AS user_count
            FROM roles r
            ORDER BY r.name ASC
        ");

        return array_map([self::class, 'decorateRole'], $roles);
    }

    /**
     * Get a single role with all its permissions
     */
    public static function getRoleWithPermissions(int $roleId): ?array
    {
        $role = self::row("SELECT id, name, slug, description, created_at FROM roles WHERE id = ?", [$roleId]);
        if (!$role) {
            return null;
        }

        $role['permissions'] = self::rows("
            SELECT p.id, p.name, p.slug, p.resource, p.action, p.description
            FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            WHERE rp.role_id = ?
            ORDER BY p.resource, p.action
        ", [$roleId]);

        return self::decorateRole($role);
    }

    /**
     * Create a role, optionally with its permissions
     *
     * @param array $data name, slug?, description?
     * @param array|null $permissionIds
     */
    public static function createRole(array $data, ?array $permissionIds = null): array
    {
        $name = trim($data['name'] ?? '');
        $slug = self::slugify(($data['slug'] ?? '') ?: $name);
        if ($name === '' || $slug === '') {
            throw new RuntimeException('Role name is required', 422);
        }

        if (self::row("SELECT id FROM roles WHERE slug = ? OR name = ?", [$slug, $name])) {
            throw new RuntimeException('A role with this name or slug already exists', 409);
        }

        $permissionIds = $permissionIds === null ? [] : self::validPermissionIds($permissionIds);

        self::transaction(function () use ($name, $slug, $data, $permissionIds, &$roleId) {
            self::run(
                "INSERT INTO roles (name, slug, description) VALUES (?, ?, ?)",
                [$name, $slug, self::nullIfEmpty($data['description'] ?? null)]
            );
            $roleId = (int)Database::lastInsertId();
            self::insertRolePermissions($roleId, $permissionIds);
        });

        return self::getRoleWithPermissions($roleId);
    }

    /**
     * Update a role (partial update: only provided fields change)
     */
    public static function updateRole(int $roleId, array $data, ?array $permissionIds = null): array
    {
        $role = self::requireRole($roleId);
        $fields = [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if ($name === '') {
                throw new RuntimeException('Role name cannot be empty', 422);
            }
            $fields['name'] = $name;
        }

        if (isset($data['slug'])) {
            $slug = self::slugify($data['slug']);
            if ($slug === '') {
                throw new RuntimeException('Role slug cannot be empty', 422);
            }
            if ($slug !== $role['slug'] && in_array($role['slug'], self::PROTECTED_ROLES, true)) {
                throw new RuntimeException('The slug of a system role cannot be changed', 403);
            }
            $fields['slug'] = $slug;
        }

        if (array_key_exists('description', $data)) {
            $fields['description'] = self::nullIfEmpty($data['description']);
        }

        if (isset($fields['name']) || isset($fields['slug'])) {
            $duplicate = self::row(
                "SELECT id FROM roles WHERE (slug = ? OR name = ?) AND id <> ?",
                [$fields['slug'] ?? $role['slug'], $fields['name'] ?? $role['name'], $roleId]
            );
            if ($duplicate) {
                throw new RuntimeException('A role with this name or slug already exists', 409);
            }
        }

        $permissionIds = $permissionIds === null ? null : self::validPermissionIds($permissionIds);

        self::transaction(function () use ($roleId, $fields, $permissionIds) {
            if ($fields) {
                self::updateFields('roles', $roleId, $fields);
            }
            if ($permissionIds !== null) {
                self::run("DELETE FROM role_permissions WHERE role_id = ?", [$roleId]);
                self::insertRolePermissions($roleId, $permissionIds);
            }
        });

        return self::getRoleWithPermissions($roleId);
    }

    /**
     * Delete a role (system roles are protected). Assignments cascade via foreign keys.
     */
    public static function deleteRole(int $roleId): void
    {
        $role = self::requireRole($roleId);

        if (in_array($role['slug'], self::PROTECTED_ROLES, true)) {
            throw new RuntimeException('System roles cannot be deleted', 403);
        }

        self::run("DELETE FROM roles WHERE id = ?", [$roleId]);
    }

    /**
     * Replace a role's permissions (an empty list clears them)
     */
    public static function setRolePermissions(int $roleId, array $permissionIds): array
    {
        return self::updateRole($roleId, [], $permissionIds);
    }

    // ==================== PERMISSIONS ====================

    /**
     * Get all permissions
     */
    public static function getAllPermissions(): array
    {
        return self::rows("
            SELECT id, name, slug, resource, action, description, created_at
            FROM permissions
            ORDER BY resource ASC, action ASC, name ASC
        ");
    }

    /**
     * Get permissions grouped by resource: [['resource' => 'users', 'permissions' => [...]], ...]
     */
    public static function getPermissionsByResource(): array
    {
        $groups = [];
        foreach (self::getAllPermissions() as $permission) {
            $groups[$permission['resource'] ?: 'other'][] = $permission;
        }

        $result = [];
        foreach ($groups as $resource => $permissions) {
            $result[] = ['resource' => $resource, 'permissions' => $permissions];
        }
        return $result;
    }

    /**
     * Create a permission
     */
    public static function createPermission(array $data): array
    {
        $name = trim($data['name'] ?? '');
        $slug = self::slugify(($data['slug'] ?? '') ?: $name);
        if ($name === '' || $slug === '') {
            throw new RuntimeException('Permission name is required', 422);
        }

        if (self::row("SELECT id FROM permissions WHERE slug = ? OR name = ?", [$slug, $name])) {
            throw new RuntimeException('A permission with this name or slug already exists', 409);
        }

        self::run(
            "INSERT INTO permissions (name, slug, resource, action, description) VALUES (?, ?, ?, ?, ?)",
            [
                $name,
                $slug,
                self::nullIfEmpty(self::slugify($data['resource'] ?? '')),
                self::nullIfEmpty(self::slugify($data['action'] ?? '')),
                self::nullIfEmpty($data['description'] ?? null),
            ]
        );

        return self::requirePermission((int)Database::lastInsertId());
    }

    /**
     * Update a permission (partial update)
     */
    public static function updatePermission(int $permissionId, array $data): array
    {
        $permission = self::requirePermission($permissionId);
        $fields = [];

        if (isset($data['name'])) {
            $fields['name'] = trim($data['name']);
            if ($fields['name'] === '') {
                throw new RuntimeException('Permission name cannot be empty', 422);
            }
        }
        if (isset($data['slug'])) {
            $fields['slug'] = self::slugify($data['slug']);
            if ($fields['slug'] === '') {
                throw new RuntimeException('Permission slug cannot be empty', 422);
            }
        }
        foreach (['resource', 'action'] as $key) {
            if (array_key_exists($key, $data)) {
                $fields[$key] = self::nullIfEmpty(self::slugify($data[$key] ?? ''));
            }
        }
        if (array_key_exists('description', $data)) {
            $fields['description'] = self::nullIfEmpty($data['description']);
        }

        if (isset($fields['name']) || isset($fields['slug'])) {
            $duplicate = self::row(
                "SELECT id FROM permissions WHERE (slug = ? OR name = ?) AND id <> ?",
                [$fields['slug'] ?? $permission['slug'], $fields['name'] ?? $permission['name'], $permissionId]
            );
            if ($duplicate) {
                throw new RuntimeException('A permission with this name or slug already exists', 409);
            }
        }

        if ($fields) {
            self::updateFields('permissions', $permissionId, $fields);
        }

        return self::requirePermission($permissionId);
    }

    /**
     * Delete a permission (role assignments cascade via foreign keys)
     */
    public static function deletePermission(int $permissionId): void
    {
        self::requirePermission($permissionId);
        self::run("DELETE FROM permissions WHERE id = ?", [$permissionId]);
    }

    // ==================== USERS ====================

    /**
     * Get a page of users with their roles
     *
     * @return array ['users' => array, 'total' => int]
     */
    public static function getUsers(int $limit = 50, int $offset = 0, string $search = ''): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $where = 'u.deleted_at IS NULL';
        $params = [];
        $search = trim($search);
        if ($search !== '') {
            $where .= " AND (u.email LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params = [$like, $like];
        }

        // LIMIT/OFFSET are clamped ints, safe to inline (native prepares reject string LIMIT params)
        $users = self::rows(
            "SELECT " . self::USER_COLUMNS . " FROM users u WHERE $where
             ORDER BY u.created_at DESC, u.id DESC
             LIMIT $limit OFFSET $offset",
            $params
        );
        $total = (int)(self::row("SELECT COUNT(*) AS count FROM users u WHERE $where", $params)['count'] ?? 0);

        // Attach roles in one query instead of one per user
        $rolesByUser = [];
        if ($users) {
            $ids = array_column($users, 'id');
            $roleRows = self::rows(
                "SELECT ur.user_id, r.id, r.name, r.slug
                 FROM user_roles ur INNER JOIN roles r ON r.id = ur.role_id
                 WHERE ur.user_id IN (" . self::placeholders($ids) . ")
                 ORDER BY r.name",
                $ids
            );
            foreach ($roleRows as $roleRow) {
                $userId = $roleRow['user_id'];
                unset($roleRow['user_id']);
                $rolesByUser[$userId][] = $roleRow;
            }
        }

        foreach ($users as &$user) {
            $user['roles'] = $rolesByUser[$user['id']] ?? [];
            $user['is_super_admin'] = self::isSuperAdminUser($user);
        }
        unset($user);

        return ['users' => $users, 'total' => $total];
    }

    /**
     * Get a user with their roles and effective permissions
     */
    public static function getUserWithPermissions(int $userId): ?array
    {
        $user = self::row("SELECT " . self::USER_COLUMNS . " FROM users u WHERE u.id = ? AND u.deleted_at IS NULL", [$userId]);
        if (!$user) {
            return null;
        }

        $user['roles'] = self::rows("
            SELECT r.id, r.name, r.slug, r.description
            FROM roles r
            INNER JOIN user_roles ur ON r.id = ur.role_id
            WHERE ur.user_id = ?
            ORDER BY r.name
        ", [$userId]);

        $user['permissions'] = self::rows("
            SELECT DISTINCT p.id, p.name, p.slug, p.resource, p.action
            FROM permissions p
            INNER JOIN role_permissions rp ON p.id = rp.permission_id
            INNER JOIN user_roles ur ON rp.role_id = ur.role_id
            WHERE ur.user_id = ?
            ORDER BY p.resource, p.action
        ", [$userId]);

        $user['is_super_admin'] = self::isSuperAdminUser($user);
        return $user;
    }

    /**
     * Create a user account (admin-created accounts are pre-verified)
     *
     * @param array $data email, password, first_name, last_name, phone?, is_active?
     */
    public static function createUser(array $data, array $roleIds = []): array
    {
        if (self::row("SELECT id FROM users WHERE email = ?", [$data['email']])) {
            throw new RuntimeException('A user with this email already exists', 409);
        }

        $roleIds = self::validRoleIds($roleIds);

        self::transaction(function () use ($data, $roleIds, &$userId) {
            self::run(
                "INSERT INTO users (email, password, first_name, last_name, phone, is_active, email_verified, email_verified_at)
                 VALUES (?, ?, ?, ?, ?, ?, 1, CURRENT_TIMESTAMP)",
                [
                    $data['email'],
                    self::hashPassword($data['password']),
                    $data['first_name'],
                    $data['last_name'],
                    self::nullIfEmpty($data['phone'] ?? null),
                    ($data['is_active'] ?? true) ? 1 : 0,
                ]
            );
            $userId = (int)Database::lastInsertId();
            self::insertUserRoles($userId, $roleIds);
        });

        return self::getUserWithPermissions($userId);
    }

    /**
     * Update a user's profile fields (email, first_name, last_name, phone)
     */
    public static function updateUser(int $userId, array $data): array
    {
        $user = self::requireUser($userId);
        $fields = array_intersect_key($data, array_flip(['email', 'first_name', 'last_name', 'phone']));

        if (isset($fields['email']) && $fields['email'] !== $user['email']) {
            if ($user['email'] === self::SYSTEM_ADMIN_EMAIL) {
                throw new RuntimeException('The system super admin email cannot be changed', 403);
            }
            if (self::row("SELECT id FROM users WHERE email = ? AND id <> ?", [$fields['email'], $userId])) {
                throw new RuntimeException('A user with this email already exists', 409);
            }
        }
        if (array_key_exists('phone', $fields)) {
            $fields['phone'] = self::nullIfEmpty($fields['phone']);
        }

        if ($fields) {
            self::updateFields('users', $userId, $fields);
        }

        return self::getUserWithPermissions($userId);
    }

    /**
     * Replace a user's roles (an empty list removes all roles)
     */
    public static function setUserRoles(int $userId, array $roleIds, int $actingUserId): array
    {
        $user = self::requireUser($userId);
        $roleIds = self::validRoleIds($roleIds);

        $superAdminRole = self::row("SELECT id FROM roles WHERE slug = 'super_admin'");
        if ($superAdminRole && !in_array((int)$superAdminRole['id'], $roleIds, true) && self::isProtectedAccount($user, $actingUserId)) {
            throw new RuntimeException('The Super Admin role cannot be removed from your own account or the system account', 403);
        }

        self::transaction(function () use ($userId, $roleIds) {
            self::run("DELETE FROM user_roles WHERE user_id = ?", [$userId]);
            self::insertUserRoles($userId, $roleIds);
        });

        return self::getUserWithPermissions($userId);
    }

    /**
     * Activate / deactivate a user
     */
    public static function setUserStatus(int $userId, bool $isActive, int $actingUserId): array
    {
        $user = self::requireUser($userId);

        if (!$isActive && self::isProtectedAccount($user, $actingUserId)) {
            throw new RuntimeException('You cannot deactivate your own account or the system account', 403);
        }

        self::run("UPDATE users SET is_active = ? WHERE id = ?", [$isActive ? 1 : 0, $userId]);
        return self::getUserWithPermissions($userId);
    }

    /**
     * Set a new password for a user (also clears any lockout)
     */
    public static function resetUserPassword(int $userId, string $password): void
    {
        self::requireUser($userId);
        self::run(
            "UPDATE users SET password = ?, failed_login_attempts = 0, locked_until = NULL WHERE id = ?",
            [self::hashPassword($password), $userId]
        );
    }

    /**
     * Soft delete a user
     */
    public static function deleteUser(int $userId, int $actingUserId): void
    {
        $user = self::requireUser($userId);

        if (self::isProtectedAccount($user, $actingUserId)) {
            throw new RuntimeException('You cannot delete your own account or the system account', 403);
        }

        self::run("UPDATE users SET deleted_at = CURRENT_TIMESTAMP, is_active = 0 WHERE id = ?", [$userId]);
    }

    // ==================== SYSTEM ====================

    /**
     * Get system statistics
     */
    public static function getSystemStats(): array
    {
        $count = fn(string $sql) => (int)(self::row($sql)['count'] ?? 0);

        return [
            'total_users' => $count("SELECT COUNT(*) AS count FROM users WHERE deleted_at IS NULL"),
            'active_users' => $count("SELECT COUNT(*) AS count FROM users WHERE is_active = 1 AND deleted_at IS NULL"),
            'total_roles' => $count("SELECT COUNT(*) AS count FROM roles"),
            'total_permissions' => $count("SELECT COUNT(*) AS count FROM permissions"),
            'total_organizations' => $count("SELECT COUNT(*) AS count FROM organizations WHERE deleted_at IS NULL"),
        ];
    }

    /**
     * Get recent audit log entries
     */
    public static function getRecentActivity(int $limit = 20): array
    {
        $limit = max(1, min(200, $limit));

        return self::rows("
            SELECT
                al.id, al.action, al.resource_type, al.resource_id, al.new_values,
                al.ip_address, al.created_at,
                u.email, u.first_name, u.last_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC, al.id DESC
            LIMIT $limit
        ");
    }

    // ==================== HELPERS ====================

    private static function requireRole(int $roleId): array
    {
        $role = self::row("SELECT id, name, slug FROM roles WHERE id = ?", [$roleId]);
        if (!$role) {
            throw new RuntimeException('Role not found', 404);
        }
        return $role;
    }

    private static function requirePermission(int $permissionId): array
    {
        $permission = self::row(
            "SELECT id, name, slug, resource, action, description, created_at FROM permissions WHERE id = ?",
            [$permissionId]
        );
        if (!$permission) {
            throw new RuntimeException('Permission not found', 404);
        }
        return $permission;
    }

    private static function requireUser(int $userId): array
    {
        $user = self::row("SELECT id, email FROM users WHERE id = ? AND deleted_at IS NULL", [$userId]);
        if (!$user) {
            throw new RuntimeException('User not found', 404);
        }
        return $user;
    }

    /**
     * Normalise ids and verify every one exists
     */
    private static function validIds(string $table, array $ids, string $label): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }

        $found = self::rows("SELECT id FROM $table WHERE id IN (" . self::placeholders($ids) . ")", $ids);
        if (count($found) !== count($ids)) {
            throw new RuntimeException("One or more $label do not exist", 422);
        }
        return $ids;
    }

    private static function validRoleIds(array $ids): array
    {
        return self::validIds('roles', $ids, 'roles');
    }

    private static function validPermissionIds(array $ids): array
    {
        return self::validIds('permissions', $ids, 'permissions');
    }

    private static function insertRolePermissions(int $roleId, array $permissionIds): void
    {
        foreach ($permissionIds as $permissionId) {
            self::run("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)", [$roleId, $permissionId]);
        }
    }

    private static function insertUserRoles(int $userId, array $roleIds): void
    {
        foreach ($roleIds as $roleId) {
            self::run("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$userId, $roleId]);
        }
    }

    /**
     * The system account and the acting admin's own account can't be locked out
     */
    private static function isProtectedAccount(array $user, int $actingUserId): bool
    {
        return $user['email'] === self::SYSTEM_ADMIN_EMAIL || (int)$user['id'] === $actingUserId;
    }

    private static function isSuperAdminUser(array $user): bool
    {
        if (($user['email'] ?? null) === self::SYSTEM_ADMIN_EMAIL) {
            return true;
        }
        return in_array('super_admin', array_column($user['roles'] ?? [], 'slug'), true);
    }

    private static function decorateRole(array $role): array
    {
        $role['is_protected'] = in_array($role['slug'], self::PROTECTED_ROLES, true);
        return $role;
    }

    private static function hashPassword(string $password): string
    {
        Config::init();
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => Config::getInt('BCRYPT_COST', 12)]);
    }

    /**
     * "Branch Manager" -> "branch_manager"
     */
    private static function slugify(?string $value): string
    {
        $slug = preg_replace('/[^a-z0-9]+/', '_', strtolower(trim((string)$value)));
        return trim($slug, '_');
    }
}
