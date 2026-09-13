<?php

namespace Ordinatrack\Api\Controller;

use Ordinatrack\Api\Helpers\ValidationHelper;
use Ordinatrack\Api\Model\MemberModel;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * BranchController
 *
 * Endpoints for a branch administrator's own branch. Every handler first resolves the
 * branch the signed-in user administers, so one branch can never read or change
 * another branch's members.
 *
 * Handlers receive the authenticated user (object), the request data (array) and, for
 * /members/{id} routes, the numeric id. A 'status' key in the returned array is the HTTP
 * status code; the route strips it before encoding.
 */
class BranchController
{
    /** Member names: letters, spaces and . ' - (e.g. "O'Brien", "Mary-Jane") */
    private const NAME_PATTERN = "/^[\p{L} .'-]+$/u";

    /** Phone numbers: digits, spaces and + ( ) -, 7 to 20 characters */
    private const PHONE_PATTERN = '/^[0-9+() -]{7,20}$/';

    /** Readable messages for the GUMP rules used here (%s = rule parameter) */
    private const RULE_MESSAGES = [
        'required' => 'is required',
        'valid_email' => 'must be a valid email address',
        'max_len' => 'must be at most %s characters',
        'date' => 'must be a valid date',
    ];

    /** Regex rules differ per field, so their messages are per field */
    private const REGEX_MESSAGES = [
        'first_name' => "may only contain letters, spaces and . ' -",
        'last_name' => "may only contain letters, spaces and . ' -",
        'phone' => 'may only contain digits, spaces and + ( ) - (7 to 20 characters)',
    ];

    /** Field names as the members form labels them */
    private const FIELD_LABELS = [
        'member_since' => 'Joined date',
    ];

    // ==================== BRANCH ====================

    /**
     * GET /branch/info - The signed-in user's branch and the member roles it offers
     */
    public static function getBranch($user, array $data): array
    {
        return self::withBranch($user, fn(array $branch) => [
            'success' => true,
            'data' => [
                'id' => (int)$branch['id'],
                'name' => $branch['name'],
                'district_name' => $branch['district_name'],
                'province_name' => $branch['province_name'],
                'member_roles' => MemberModel::ROLES,
            ],
        ]);
    }

    // ==================== MEMBERS ====================

    /**
     * GET /branch/members?search=&status=&role=&limit=&offset=
     */
    public static function getMembers($user, array $data): array
    {
        return self::withBranch($user, function (array $branch) use ($data) {
            $result = MemberModel::getAll(
                self::organizationId($branch),
                $data,
                (int)($data['limit'] ?? 20),
                (int)($data['offset'] ?? 0)
            );
            return ['success' => true, 'data' => $result['members'], 'total' => $result['total']];
        });
    }

    /**
     * GET /branch/members/{id}
     */
    public static function getMember($user, array $data, int $id): array
    {
        return self::withBranch($user, function (array $branch) use ($id) {
            $organizationId = self::organizationId($branch);
            $member = $organizationId ? MemberModel::getOne($organizationId, $id) : null;
            if (!$member) {
                return ['success' => false, 'message' => 'Member not found', 'status' => 404];
            }
            return ['success' => true, 'data' => $member];
        });
    }

    /**
     * POST /branch/members
     */
    public static function createMember($user, array $data): array
    {
        return self::withBranch($user, function (array $branch) use ($user, $data) {
            [$input, $error] = self::memberInput($data, true);
            if ($error) {
                return $error;
            }
            $role = $input['role'] ?? MemberModel::ROLES[0];
            unset($input['role']);

            $member = MemberModel::create($branch, (int)$user->id, $input, $role);
            return ['success' => true, 'message' => 'Member added', 'data' => $member, 'status' => 201];
        });
    }

    /**
     * PUT /branch/members/{id} - Partial update
     */
    public static function updateMember($user, array $data, int $id): array
    {
        return self::withBranch($user, function (array $branch) use ($data, $id) {
            $organizationId = self::organizationId($branch);
            if (!$organizationId) {
                return ['success' => false, 'message' => 'Member not found', 'status' => 404];
            }
            [$input, $error] = self::memberInput($data, false);
            if ($error) {
                return $error;
            }
            $role = $input['role'] ?? null;
            unset($input['role']);

            $member = MemberModel::update($organizationId, $id, $input, $role);
            return ['success' => true, 'message' => 'Member updated', 'data' => $member];
        });
    }

    /**
     * DELETE /branch/members/{id}
     */
    public static function deleteMember($user, array $data, int $id): array
    {
        return self::withBranch($user, function (array $branch) use ($id) {
            $organizationId = self::organizationId($branch);
            if (!$organizationId) {
                return ['success' => false, 'message' => 'Member not found', 'status' => 404];
            }
            MemberModel::delete($organizationId, $id);
            return ['success' => true, 'message' => 'Member removed'];
        });
    }

    // ==================== HELPERS ====================

    /**
     * Resolve the user's branch and run the handler with it, mapping model exceptions
     * to error responses. Users who don't administer a branch get a NO_BRANCH error.
     */
    private static function withBranch($user, callable $action): array
    {
        try {
            $branch = MemberModel::branchFor((int)$user->id);
            if (!$branch) {
                return [
                    'success' => false,
                    'code' => 'NO_BRANCH',
                    'message' => 'Ask your super admin to open Locations, find your branch and set you as its '
                        . "Administrator. You'll be able to manage members here once that's done.",
                    'status' => 409,
                ];
            }
            return $action($branch);
        } catch (PDOException $e) {
            error_log('Branch database error: ' . $e->getMessage());
        } catch (RuntimeException $e) {
            // Business-rule failures carry their HTTP status (4xx) as the exception code
            $status = (int)$e->getCode();
            if ($status >= 400 && $status < 500) {
                return ['success' => false, 'message' => $e->getMessage(), 'status' => $status];
            }
            error_log('Branch error: ' . $e->getMessage());
        } catch (Throwable $e) {
            error_log("Branch error: {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}");
        }

        return ['success' => false, 'message' => 'An unexpected error occurred', 'status' => 500];
    }

    private static function organizationId(array $branch): ?int
    {
        return $branch['organization_id'] ? (int)$branch['organization_id'] : null;
    }

    /**
     * Validate member input: text fields through GUMP, the role and active flag here.
     * On update only the fields present in the request are validated.
     * Returns [input, null] on success or [null, errorResponse].
     */
    private static function memberInput(array $data, bool $creating): array
    {
        $rules = [
            'first_name' => ['required', 'regex' => [self::NAME_PATTERN], 'max_len' => 100],
            'last_name' => ['required', 'regex' => [self::NAME_PATTERN], 'max_len' => 100],
            'email' => 'valid_email|max_len,255',
            'phone' => ['regex' => [self::PHONE_PATTERN]],
            'member_since' => 'date,Y-m-d',
        ];
        if (!$creating) {
            $rules = array_intersect_key($rules, $data);
        }

        $input = [];
        if ($rules) {
            $validation = ValidationHelper::validate($data, $rules);
            if (!$validation['is_valid']) {
                return [null, self::validationError($validation['errors'])];
            }
            // Blank optional fields are stored as NULL
            foreach (array_intersect_key($validation['data'], $rules) as $key => $value) {
                $value = is_string($value) ? trim($value) : $value;
                $input[$key] = $value === '' ? null : $value;
            }
        }

        if (!empty($input['member_since']) && $input['member_since'] > date('Y-m-d')) {
            return [null, ['success' => false, 'message' => 'Joined date cannot be in the future', 'status' => 422]];
        }

        if (array_key_exists('role', $data)) {
            if (!in_array($data['role'], MemberModel::ROLES, true)) {
                $roles = implode(', ', MemberModel::ROLES);
                return [null, ['success' => false, 'message' => "Role must be one of: $roles", 'status' => 422]];
            }
            $input['role'] = $data['role'];
        }

        if (array_key_exists('is_active', $data)) {
            $active = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active === null) {
                return [null, ['success' => false, 'message' => 'Status must be active or inactive', 'status' => 422]];
            }
            $input['is_active'] = $active ? 1 : 0;
        }

        if (!$creating && !$input) {
            return [null, ['success' => false, 'message' => 'No fields to update', 'status' => 422]];
        }
        return [$input, null];
    }

    /**
     * Readable per-field messages. GUMP's raw errors echo the submitted values, so
     * they are never returned as-is.
     */
    private static function validationError(array $gumpErrors): array
    {
        $errors = [];
        foreach ($gumpErrors as $key => $error) {
            if (!is_array($error)) {
                $errors[$key] = (string)$error;
                continue;
            }
            $field = $error['field'] ?? $key;
            $rule = $error['rule'] ?? '';
            $template = $rule === 'regex'
                ? (self::REGEX_MESSAGES[$field] ?? 'is invalid')
                : (self::RULE_MESSAGES[$rule] ?? 'is invalid');
            $label = self::FIELD_LABELS[$field] ?? ucfirst(str_replace('_', ' ', $field));
            $errors[$field] = $label . ' ' . vsprintf($template, (array)($error['params'] ?? []));
        }

        return ['success' => false, 'message' => implode('. ', $errors), 'errors' => $errors, 'status' => 422];
    }
}
