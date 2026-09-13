<?php

namespace Ordinatrack\Api\Controller;

use Ordinatrack\Api\Helpers\LocationValidator;
use Ordinatrack\Api\Helpers\ValidationHelper;
use Ordinatrack\Api\Model\LocationModel;
use Ordinatrack\Api\Model\SuperAdminModel;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * SuperAdminController
 *
 * Handles all Super Admin API endpoints for:
 * - Role management
 * - Permission management
 * - User management
 * - System statistics
 *
 * Handlers receive the authenticated user (object), the request data (array) and,
 * for /{resource}/{id} routes, the numeric id. A 'status' key in the returned array
 * is the HTTP status code; the route strips it before encoding.
 *
 * Note: Authorization is handled by SuperAdminMiddleware in the routes
 */
class SuperAdminController
{
    private const NAME_RULE = 'required|alpha_space|max_len,50';
    private const PASSWORD_RULE = 'required|min_len,8|max_len,128';

    /** Readable messages for the GUMP rules used here (%s = rule parameter) */
    private const RULE_MESSAGES = [
        'required' => 'is required',
        'valid_email' => 'must be a valid email address',
        'min_len' => 'must be at least %s characters',
        'max_len' => 'must be at most %s characters',
        'alpha_space' => 'may only contain letters and spaces',
    ];

    // ==================== ROLES ENDPOINTS ====================

    /**
     * GET /admin/roles - Get all roles
     */
    public static function getRoles($user, array $data): array
    {
        return self::handle(function () {
            $roles = SuperAdminModel::getAllRoles();
            return ['success' => true, 'data' => $roles, 'count' => count($roles)];
        });
    }

    /**
     * GET /admin/roles/:id - Get role with permissions
     */
    public static function getRole($user, array $data, int $id): array
    {
        return self::handle(function () use ($id) {
            $role = SuperAdminModel::getRoleWithPermissions($id);
            return $role
                ? ['success' => true, 'data' => $role]
                : ['success' => false, 'message' => 'Role not found', 'status' => 404];
        });
    }

    /**
     * POST /admin/roles - Create role {name, slug?, description?, permission_ids?}
     */
    public static function createRole($user, array $data): array
    {
        return self::handle(function () use ($data) {
            $role = SuperAdminModel::createRole($data, self::idList($data, 'permission_ids', false));
            return ['success' => true, 'message' => 'Role created successfully', 'data' => $role, 'status' => 201];
        });
    }

    /**
     * PUT /admin/roles/:id - Update role {name?, slug?, description?, permission_ids?}
     */
    public static function updateRole($user, array $data, int $id): array
    {
        return self::handle(function () use ($data, $id) {
            $role = SuperAdminModel::updateRole($id, $data, self::idList($data, 'permission_ids', false));
            return ['success' => true, 'message' => 'Role updated successfully', 'data' => $role];
        });
    }

    /**
     * DELETE /admin/roles/:id - Delete role
     */
    public static function deleteRole($user, array $data, int $id): array
    {
        return self::handle(function () use ($id) {
            SuperAdminModel::deleteRole($id);
            return ['success' => true, 'message' => 'Role deleted successfully'];
        });
    }

    /**
     * PUT /admin/roles/:id/permissions - Replace role permissions {permission_ids: []} ([] clears)
     */
    public static function setRolePermissions($user, array $data, int $id): array
    {
        return self::handle(function () use ($data, $id) {
            $role = SuperAdminModel::setRolePermissions($id, self::idList($data, 'permission_ids'));
            return ['success' => true, 'message' => 'Role permissions updated successfully', 'data' => $role];
        });
    }

    // ==================== PERMISSIONS ENDPOINTS ====================

    /**
     * GET /admin/permissions - Get all permissions
     */
    public static function getPermissions($user, array $data): array
    {
        return self::handle(function () {
            $permissions = SuperAdminModel::getAllPermissions();
            return ['success' => true, 'data' => $permissions, 'count' => count($permissions)];
        });
    }

    /**
     * GET /admin/permissions/by-resource - Get permissions grouped by resource
     */
    public static function getPermissionsByResource($user, array $data): array
    {
        return self::handle(fn() => ['success' => true, 'data' => SuperAdminModel::getPermissionsByResource()]);
    }

    /**
     * POST /admin/permissions - Create permission {name, slug?, resource?, action?, description?}
     */
    public static function createPermission($user, array $data): array
    {
        return self::handle(function () use ($data) {
            $permission = SuperAdminModel::createPermission($data);
            return ['success' => true, 'message' => 'Permission created successfully', 'data' => $permission, 'status' => 201];
        });
    }

    /**
     * PUT /admin/permissions/:id - Update permission
     */
    public static function updatePermission($user, array $data, int $id): array
    {
        return self::handle(function () use ($data, $id) {
            $permission = SuperAdminModel::updatePermission($id, $data);
            return ['success' => true, 'message' => 'Permission updated successfully', 'data' => $permission];
        });
    }

    /**
     * DELETE /admin/permissions/:id - Delete permission
     */
    public static function deletePermission($user, array $data, int $id): array
    {
        return self::handle(function () use ($id) {
            SuperAdminModel::deletePermission($id);
            return ['success' => true, 'message' => 'Permission deleted successfully'];
        });
    }

    // ==================== USERS ENDPOINTS ====================

    /**
     * GET /admin/users?limit=&offset=&search= - Get a page of users
     */
    public static function getUsers($user, array $data): array
    {
        return self::handle(function () use ($data) {
            $limit = (int)($data['limit'] ?? 50);
            $offset = (int)($data['offset'] ?? 0);
            $result = SuperAdminModel::getUsers($limit, $offset, (string)($data['search'] ?? ''));

            return [
                'success' => true,
                'data' => $result['users'],
                'pagination' => ['limit' => $limit, 'offset' => $offset, 'total' => $result['total']],
            ];
        });
    }

    /**
     * GET /admin/users/:id - Get user with roles and permissions
     */
    public static function getUser($user, array $data, int $id): array
    {
        return self::handle(function () use ($id) {
            $userData = SuperAdminModel::getUserWithPermissions($id);
            return $userData
                ? ['success' => true, 'data' => $userData]
                : ['success' => false, 'message' => 'User not found', 'status' => 404];
        });
    }

    /**
     * POST /admin/users - Create user {email, password, first_name, last_name, phone?, is_active?, role_ids?}
     */
    public static function createUser($user, array $data): array
    {
        return self::handle(function () use ($data) {
            [$input, $error] = self::validateInput($data, [
                'email' => 'required|valid_email',
                'password' => self::PASSWORD_RULE,
                'first_name' => self::NAME_RULE,
                'last_name' => self::NAME_RULE,
                'phone' => 'max_len,20',
            ]);
            if ($error) {
                return $error;
            }
            if (!ValidationHelper::isStrongPassword($input['password'])) {
                return self::weakPasswordError();
            }

            $input['email'] = strtolower($input['email']);
            $input['is_active'] = self::bool($data, 'is_active') ?? true;

            $created = SuperAdminModel::createUser($input, self::idList($data, 'role_ids', false) ?? []);
            return ['success' => true, 'message' => 'User created successfully', 'data' => $created, 'status' => 201];
        });
    }

    /**
     * PUT /admin/users/:id - Update profile {email?, first_name?, last_name?, phone?}
     */
    public static function updateUser($user, array $data, int $id): array
    {
        return self::handle(function () use ($data, $id) {
            $rules = array_intersect_key([
                'email' => 'required|valid_email',
                'first_name' => self::NAME_RULE,
                'last_name' => self::NAME_RULE,
                'phone' => 'max_len,20',
            ], $data);
            if (!$rules) {
                return ['success' => false, 'message' => 'No fields to update', 'status' => 422];
            }

            [$input, $error] = self::validateInput($data, $rules);
            if ($error) {
                return $error;
            }
            if (isset($input['email'])) {
                $input['email'] = strtolower($input['email']);
            }

            $updated = SuperAdminModel::updateUser($id, $input);
            return ['success' => true, 'message' => 'User updated successfully', 'data' => $updated];
        });
    }

    /**
     * PUT /admin/users/:id/roles - Replace user roles {role_ids: []} ([] removes all)
     */
    public static function setUserRoles($user, array $data, int $id): array
    {
        return self::handle(function () use ($user, $data, $id) {
            $updated = SuperAdminModel::setUserRoles($id, self::idList($data, 'role_ids'), (int)$user->id);
            return ['success' => true, 'message' => 'User roles updated successfully', 'data' => $updated];
        });
    }

    /**
     * PUT /admin/users/:id/status - Activate/deactivate {is_active: bool}
     */
    public static function setUserStatus($user, array $data, int $id): array
    {
        return self::handle(function () use ($user, $data, $id) {
            $isActive = self::bool($data, 'is_active');
            if ($isActive === null) {
                return ['success' => false, 'message' => 'Missing required field: is_active (boolean)', 'status' => 422];
            }

            $updated = SuperAdminModel::setUserStatus($id, $isActive, (int)$user->id);
            return [
                'success' => true,
                'message' => $isActive ? 'User activated successfully' : 'User deactivated successfully',
                'data' => $updated,
            ];
        });
    }

    /**
     * PUT /admin/users/:id/password - Set a new password {password}
     */
    public static function resetUserPassword($user, array $data, int $id): array
    {
        return self::handle(function () use ($data, $id) {
            [$input, $error] = self::validateInput($data, ['password' => self::PASSWORD_RULE]);
            if ($error) {
                return $error;
            }
            if (!ValidationHelper::isStrongPassword($input['password'])) {
                return self::weakPasswordError();
            }

            SuperAdminModel::resetUserPassword($id, $input['password']);
            return ['success' => true, 'message' => 'Password reset successfully'];
        });
    }

    /**
     * DELETE /admin/users/:id - Delete user (soft delete)
     */
    public static function deleteUser($user, array $data, int $id): array
    {
        return self::handle(function () use ($user, $id) {
            SuperAdminModel::deleteUser($id, (int)$user->id);
            return ['success' => true, 'message' => 'User deleted successfully'];
        });
    }

    // ==================== LOCATIONS ENDPOINTS (provinces / districts / branches) ====================

    /**
     * GET /admin/{provinces|districts|branches}?province_id=&district_id=&search=
     */
    public static function listLocations($user, array $data, string $type): array
    {
        return self::handle(function () use ($data, $type) {
            $locations = LocationModel::getAll($type, $data);
            return ['success' => true, 'data' => $locations, 'count' => count($locations)];
        });
    }

    /**
     * POST /admin/{type} - Create {name, code?, description?, administrator_id?, is_active?,
     * province_id (districts) | district_id, address, contact_* (branches)}
     */
    public static function createLocation($user, array $data, string $type): array
    {
        return self::handle(function () use ($data, $type) {
            [$input, $error] = self::locationInput($data, $type, true);
            if ($error) {
                return $error;
            }

            $location = LocationModel::create($type, $input);
            return [
                'success' => true,
                'message' => LocationModel::label($type) . ' created successfully',
                'data' => $location,
                'status' => 201,
            ];
        });
    }

    /**
     * PUT /admin/{type}/:id - Partial update (changing the parent moves the location)
     */
    public static function updateLocation($user, array $data, string $type, int $id): array
    {
        return self::handle(function () use ($data, $type, $id) {
            [$input, $error] = self::locationInput($data, $type, false);
            if ($error) {
                return $error;
            }

            $location = LocationModel::update($type, $id, $input);
            return ['success' => true, 'message' => LocationModel::label($type) . ' updated successfully', 'data' => $location];
        });
    }

    /**
     * DELETE /admin/{type}/:id - Only locations without children can be deleted
     */
    public static function deleteLocation($user, array $data, string $type, int $id): array
    {
        return self::handle(function () use ($type, $id) {
            LocationModel::delete($type, $id);
            return ['success' => true, 'message' => LocationModel::label($type) . ' deleted successfully'];
        });
    }

    // ==================== SYSTEM ENDPOINTS ====================

    /**
     * GET /admin/stats - Get system statistics
     */
    public static function getSystemStats($user, array $data): array
    {
        return self::handle(fn() => ['success' => true, 'data' => SuperAdminModel::getSystemStats()]);
    }

    /**
     * GET /admin/activity?limit= - Get recent audit log entries
     */
    public static function getRecentActivity($user, array $data): array
    {
        return self::handle(function () use ($data) {
            $activity = SuperAdminModel::getRecentActivity((int)($data['limit'] ?? 20));
            return ['success' => true, 'data' => $activity, 'count' => count($activity)];
        });
    }

    // ==================== HELPERS ====================

    /**
     * Run a handler, mapping model exceptions to error responses
     */
    private static function handle(callable $action): array
    {
        try {
            return $action();
        } catch (PDOException $e) {
            error_log('Super admin database error: ' . $e->getMessage());
        } catch (RuntimeException $e) {
            // Business-rule failures carry their HTTP status (4xx) as the exception code
            $status = (int)$e->getCode();
            if ($status >= 400 && $status < 500) {
                return ['success' => false, 'message' => $e->getMessage(), 'status' => $status];
            }
            error_log('Super admin error: ' . $e->getMessage());
        } catch (Throwable $e) {
            error_log("Super admin error: {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}");
        }

        return ['success' => false, 'message' => 'An unexpected error occurred', 'status' => 500];
    }

    /**
     * Validate with GUMP rules. Returns [input, null] on success or [null, errorResponse].
     * GUMP's raw errors echo submitted values (passwords included), so only readable
     * per-field messages are returned.
     */
    private static function validateInput(array $data, array $rules): array
    {
        $validation = ValidationHelper::validate($data, $rules);
        if (!$validation['is_valid']) {
            $errors = [];
            foreach ($validation['errors'] as $key => $error) {
                if (!is_array($error)) {
                    $errors[$key] = (string)$error;
                    continue;
                }
                $field = $error['field'] ?? $key;
                $template = self::RULE_MESSAGES[$error['rule'] ?? ''] ?? 'is invalid';
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' ' . vsprintf($template, (array)($error['params'] ?? []));
            }
            return [null, ['success' => false, 'message' => implode('. ', $errors), 'errors' => $errors, 'status' => 422]];
        }

        $input = $validation['data'];
        foreach ($input as $key => $value) {
            if (is_string($value) && $key !== 'password') {
                $input[$key] = trim($value);
            }
        }
        return [$input, null];
    }

    /**
     * Location input rules are shared with the district dashboard, so district leads
     * and the super admin validate branches the same way.
     */
    private static function locationInput(array $data, string $type, bool $creating): array
    {
        return LocationValidator::input($data, $type, $creating);
    }

    private static function weakPasswordError(): array
    {
        return [
            'success' => false,
            'message' => 'Password must contain uppercase, lowercase, number and special character',
            'errors' => ['password' => 'Password too weak'],
            'status' => 422,
        ];
    }

    /**
     * Read an array of numeric ids from the request.
     * When not required, a missing key returns null (meaning "leave unchanged").
     */
    private static function idList(array $data, string $key, bool $required = true): ?array
    {
        if (!isset($data[$key])) {
            if ($required) {
                throw new RuntimeException("Missing required field: $key (array)", 422);
            }
            return null;
        }
        if (!is_array($data[$key]) || array_filter($data[$key], fn($id) => !is_numeric($id))) {
            throw new RuntimeException("Field $key must be an array of ids", 422);
        }
        return $data[$key];
    }

    private static function bool(array $data, string $key): ?bool
    {
        if (!array_key_exists($key, $data)) {
            return null;
        }
        return filter_var($data[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }
}
