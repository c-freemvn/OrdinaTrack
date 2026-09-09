<?php

namespace Ordinatrack\Api\Controller;

use Ordinatrack\Api\Model\SuperAdminModel;

/**
 * SuperAdminController
 * 
 * Handles all Super Admin API endpoints for:
 * - Role management
 * - Permission management
 * - User management
 * - System statistics
 * 
 * Note: Authorization is handled by SuperAdminMiddleware in the routes
 */
class SuperAdminController
{
    // ==================== ROLES ENDPOINTS ====================

    /**
     * GET /admin/roles - Get all roles
     */
    public static function getRoles($user, $method, $body)
    {
        $roles = SuperAdminModel::getAllRoles();
        
        if ($roles === false) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve roles',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'data' => $roles,
            'count' => count($roles)
        ];
    }

    /**
     * GET /admin/roles/:id - Get role with permissions
     */
    public static function getRole($user, $method, $body, $id)
    {
        $role = SuperAdminModel::getRoleWithPermissions($id);
        
        if (!$role) {
            return [
                'success' => false,
                'message' => 'Role not found',
                'status' => 404
            ];
        }

        return [
            'success' => true,
            'data' => $role
        ];
    }

    /**
     * POST /admin/roles - Create new role
     */
    public static function createRole($user, $method, $body)
    {
        if (empty($body->name) || empty($body->slug)) {
            return [
                'success' => false,
                'message' => 'Missing required fields: name, slug',
                'status' => 400
            ];
        }

        $roleData = [
            'name' => $body->name,
            'slug' => strtolower(str_replace(' ', '_', $body->slug)),
            'description' => $body->description ?? null
        ];

        $role = SuperAdminModel::createRole($roleData);
        
        if (!$role) {
            return [
                'success' => false,
                'message' => 'Failed to create role',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role
        ];
    }

    /**
     * PUT /admin/roles/:id - Update role
     */
    public static function updateRole($user, $method, $body, $id)
    {
        $roleData = [
            'name' => $body->name ?? null,
            'slug' => isset($body->slug) ? strtolower(str_replace(' ', '_', $body->slug)) : null,
            'description' => $body->description ?? null
        ];

        $role = SuperAdminModel::updateRole($id, $roleData);
        
        if (!$role) {
            return [
                'success' => false,
                'message' => 'Failed to update role',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role
        ];
    }

    /**
     * DELETE /admin/roles/:id - Delete role
     */
    public static function deleteRole($user, $method, $body, $id)
    {
        $result = SuperAdminModel::deleteRole($id);
        
        if (!$result) {
            return [
                'success' => false,
                'message' => 'Failed to delete role or role is protected',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'message' => 'Role deleted successfully'
        ];
    }

    // ==================== PERMISSIONS ENDPOINTS ====================

    /**
     * GET /admin/permissions - Get all permissions
     */
    public static function getPermissions($user, $method, $body)
    {
        $permissions = SuperAdminModel::getAllPermissions();
        
        if ($permissions === false) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve permissions',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'data' => $permissions,
            'count' => count($permissions)
        ];
    }

    /**
     * GET /admin/permissions/by-resource - Get permissions grouped by resource
     */
    public static function getPermissionsByResource($user, $method, $body)
    {
        $permissions = SuperAdminModel::getPermissionsByResource();
        
        if ($permissions === false) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve permissions',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'data' => $permissions
        ];
    }

    /**
     * POST /admin/permissions - Create new permission
     */
    public static function createPermission($user, $method, $body)
    {
        if (empty($body->name) || empty($body->slug)) {
            return [
                'success' => false,
                'message' => 'Missing required fields: name, slug',
                'status' => 400
            ];
        }

        $permData = [
            'name' => $body->name,
            'slug' => strtolower(str_replace(' ', '_', $body->slug)),
            'resource' => $body->resource ?? null,
            'action' => $body->action ?? null,
            'description' => $body->description ?? null
        ];

        $permission = SuperAdminModel::createPermission($permData);
        
        if (!$permission) {
            return [
                'success' => false,
                'message' => 'Failed to create permission',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'message' => 'Permission created successfully',
            'data' => $permission
        ];
    }

    /**
     * PUT /admin/permissions/:id - Update permission
     */
    public static function updatePermission($user, $method, $body, $id)
    {
        $permData = [
            'name' => $body->name ?? null,
            'slug' => isset($body->slug) ? strtolower(str_replace(' ', '_', $body->slug)) : null,
            'resource' => $body->resource ?? null,
            'action' => $body->action ?? null,
            'description' => $body->description ?? null
        ];

        $permission = SuperAdminModel::updatePermission($id, $permData);
        
        if (!$permission) {
            return [
                'success' => false,
                'message' => 'Failed to update permission',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'message' => 'Permission updated successfully',
            'data' => $permission
        ];
    }

    /**
     * DELETE /admin/permissions/:id - Delete permission
     */
    public static function deletePermission($user, $method, $body, $id)
    {
        $result = SuperAdminModel::deletePermission($id);
        
        if (!$result) {
            return [
                'success' => false,
                'message' => 'Failed to delete permission',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'message' => 'Permission deleted successfully'
        ];
    }

    /**
     * POST /admin/roles/:id/permissions - Assign permissions to role
     */
    public static function assignPermissionsToRole($user, $method, $body, $id)
    {
        if (empty($body->permission_ids) || !is_array($body->permission_ids)) {
            return [
                'success' => false,
                'message' => 'Missing required field: permission_ids (array)',
                'status' => 400
            ];
        }

        $result = SuperAdminModel::assignPermissionsToRole($id, $body->permission_ids);
        
        if (!$result) {
            return [
                'success' => false,
                'message' => 'Failed to assign permissions to role',
                'status' => 500
            ];
        }

        $role = SuperAdminModel::getRoleWithPermissions($id);

        return [
            'success' => true,
            'message' => 'Permissions assigned to role successfully',
            'data' => $role
        ];
    }

    // ==================== USERS ENDPOINTS ====================

    /**
     * GET /admin/users - Get all users
     */
    public static function getUsers($user, $method, $body)
    {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

        $users = SuperAdminModel::getAllUsers($limit, $offset);
        $total = SuperAdminModel::getUserCount();
        
        if ($users === false) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve users',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'data' => $users,
            'pagination' => [
                'limit' => $limit,
                'offset' => $offset,
                'total' => $total
            ]
        ];
    }

    /**
     * GET /admin/users/:id - Get user with permissions
     */
    public static function getUser($user, $method, $body, $id)
    {
        $userData = SuperAdminModel::getUserWithPermissions($id);
        
        if (!$userData) {
            return [
                'success' => false,
                'message' => 'User not found',
                'status' => 404
            ];
        }

        return [
            'success' => true,
            'data' => $userData
        ];
    }

    /**
     * PUT /admin/users/:id/roles - Assign roles to user
     */
    public static function assignRolesToUser($user, $method, $body, $id)
    {
        if (empty($body->role_ids) || !is_array($body->role_ids)) {
            return [
                'success' => false,
                'message' => 'Missing required field: role_ids (array)',
                'status' => 400
            ];
        }

        $result = SuperAdminModel::assignRolesToUser($id, $body->role_ids);
        
        if (!$result) {
            return [
                'success' => false,
                'message' => 'Failed to assign roles to user',
                'status' => 500
            ];
        }

        $userData = SuperAdminModel::getUserWithPermissions($id);

        return [
            'success' => true,
            'message' => 'Roles assigned to user successfully',
            'data' => $userData
        ];
    }

    /**
     * PUT /admin/users/:id/status - Update user status
     */
    public static function updateUserStatus($user, $method, $body, $id)
    {
        if (!isset($body->is_active)) {
            return [
                'success' => false,
                'message' => 'Missing required field: is_active (boolean)',
                'status' => 400
            ];
        }

        $userData = SuperAdminModel::updateUserStatus($id, $body->is_active);
        
        if (!$userData) {
            return [
                'success' => false,
                'message' => 'Failed to update user status',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => $userData
        ];
    }

    /**
     * DELETE /admin/users/:id - Delete user (soft delete)
     */
    public static function deleteUser($user, $method, $body, $id)
    {
        $result = SuperAdminModel::deleteUser($id);
        
        if (!$result) {
            return [
                'success' => false,
                'message' => 'Failed to delete user or user is protected',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'message' => 'User deleted successfully'
        ];
    }

    // ==================== SYSTEM ENDPOINTS ====================

    /**
     * GET /admin/stats - Get system statistics
     */
    public static function getSystemStats($user, $method, $body)
    {
        $stats = SuperAdminModel::getSystemStats();

        return [
            'success' => true,
            'data' => $stats
        ];
    }

    /**
     * GET /admin/activity - Get recent activity logs
     */
    public static function getRecentActivity($user, $method, $body)
    {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
        $activity = SuperAdminModel::getRecentActivity($limit);

        if ($activity === false) {
            return [
                'success' => false,
                'message' => 'Failed to retrieve activity logs',
                'status' => 500
            ];
        }

        return [
            'success' => true,
            'data' => $activity,
            'count' => count($activity)
        ];
    }
}
