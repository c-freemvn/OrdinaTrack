<?php

namespace Ordinatrack\Api\Controller;

use Ordinatrack\Api\Helpers\LocationValidator;
use Ordinatrack\Api\Model\DistrictModel;
use Ordinatrack\Api\Model\LocationModel;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * DistrictController
 *
 * Endpoints for a district lead's own district. Every handler first resolves the district
 * the signed-in user administers, so one district can never read or change another
 * district's branches or members.
 *
 * Branches are written through LocationModel (the same path the super admin uses), with
 * the district forced to the lead's own and the branch administrator left alone, since
 * assigning administrators stays a super admin action.
 *
 * Handlers receive the authenticated user (object), the request data (array) and, for
 * /branches/{id} routes, the numeric id. A 'status' key in the returned array is the HTTP
 * status code; the route strips it before encoding.
 */
class DistrictController
{
    // ==================== DISTRICT ====================

    /**
     * GET /district/info - The signed-in user's district and its headline numbers
     */
    public static function getDistrict($user, array $data): array
    {
        return self::withDistrict($user, fn(array $district) => [
            'success' => true,
            'data' => [
                'id' => (int)$district['id'],
                'name' => $district['name'],
                'code' => $district['code'],
                'province_name' => $district['province_name'],
                'stats' => DistrictModel::stats((int)$district['id']),
            ],
        ]);
    }

    /**
     * GET /district/activity?limit= - What the district's administrators have been doing
     */
    public static function getActivity($user, array $data): array
    {
        return self::withDistrict($user, function (array $district) use ($data) {
            $activity = DistrictModel::getActivity((int)$district['id'], (int)($data['limit'] ?? 20));
            return ['success' => true, 'data' => $activity, 'count' => count($activity)];
        });
    }

    // ==================== BRANCHES ====================

    /**
     * GET /district/branches?search=&status=&limit=&offset=
     */
    public static function getBranches($user, array $data): array
    {
        return self::withDistrict($user, function (array $district) use ($data) {
            $result = DistrictModel::getBranches(
                (int)$district['id'],
                $data,
                (int)($data['limit'] ?? 20),
                (int)($data['offset'] ?? 0)
            );
            return ['success' => true, 'data' => $result['branches'], 'total' => $result['total']];
        });
    }

    /**
     * GET /district/branches/{id}
     */
    public static function getBranch($user, array $data, int $id): array
    {
        return self::withDistrict($user, function (array $district) use ($id) {
            $branch = DistrictModel::getBranch((int)$district['id'], $id);
            if (!$branch) {
                return ['success' => false, 'message' => 'Branch not found in your district', 'status' => 404];
            }
            return ['success' => true, 'data' => $branch];
        });
    }

    /**
     * POST /district/branches - {name, code?, description?, address?, contact_*?, is_active?}
     */
    public static function createBranch($user, array $data): array
    {
        return self::withDistrict($user, function (array $district) use ($data) {
            [$input, $error] = LocationValidator::input($data, 'branches', true);
            if ($error) {
                return $error;
            }

            $input = self::ownBranchInput($input, (int)$district['id']);
            $created = LocationModel::create('branches', $input);
            $branch = DistrictModel::getBranch((int)$district['id'], (int)$created['id']);

            return ['success' => true, 'message' => 'Branch created', 'data' => $branch, 'status' => 201];
        });
    }

    /**
     * PUT /district/branches/{id} - Partial update
     */
    public static function updateBranch($user, array $data, int $id): array
    {
        return self::withDistrict($user, function (array $district) use ($data, $id) {
            DistrictModel::requireBranch((int)$district['id'], $id);

            [$input, $error] = LocationValidator::input($data, 'branches', false);
            if ($error) {
                return $error;
            }

            LocationModel::update('branches', $id, self::ownBranchInput($input, (int)$district['id']));
            $branch = DistrictModel::getBranch((int)$district['id'], $id);

            return ['success' => true, 'message' => 'Branch updated', 'data' => $branch];
        });
    }

    /**
     * DELETE /district/branches/{id} - Refused while the branch still has members
     */
    public static function deleteBranch($user, array $data, int $id): array
    {
        return self::withDistrict($user, function (array $district) use ($id) {
            DistrictModel::deleteBranch((int)$district['id'], $id);
            return ['success' => true, 'message' => 'Branch deleted'];
        });
    }

    // ==================== MEMBERS ====================

    /**
     * GET /district/members?search=&status=&role=&branch_id=&limit=&offset=
     *
     * Read-only: members are added and edited by their own branch administrator.
     */
    public static function getMembers($user, array $data): array
    {
        return self::withDistrict($user, function (array $district) use ($data) {
            $result = DistrictModel::getMembers(
                (int)$district['id'],
                $data,
                (int)($data['limit'] ?? 20),
                (int)($data['offset'] ?? 0)
            );
            return ['success' => true, 'data' => $result['members'], 'total' => $result['total']];
        });
    }

    // ==================== HELPERS ====================

    /**
     * Resolve the user's district and run the handler with it, mapping model exceptions
     * to error responses. Users who don't administer a district get a NO_DISTRICT error.
     */
    private static function withDistrict($user, callable $action): array
    {
        try {
            $district = DistrictModel::districtFor((int)$user->id);
            if (!$district) {
                return [
                    'success' => false,
                    'code' => 'NO_DISTRICT',
                    'message' => 'Ask your super admin to open Locations, find your district and set you as its '
                        . "Administrator. You'll be able to manage its branches here once that's done.",
                    'status' => 409,
                ];
            }
            return $action($district);
        } catch (PDOException $e) {
            error_log('District database error: ' . $e->getMessage());
        } catch (RuntimeException $e) {
            // Business-rule failures carry their HTTP status (4xx) as the exception code
            $status = (int)$e->getCode();
            if ($status >= 400 && $status < 500) {
                return ['success' => false, 'message' => $e->getMessage(), 'status' => $status];
            }
            error_log('District error: ' . $e->getMessage());
        } catch (Throwable $e) {
            error_log("District error: {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}");
        }

        return ['success' => false, 'message' => 'An unexpected error occurred', 'status' => 500];
    }

    /**
     * Branches always belong to the lead's own district, and only a super admin
     * assigns branch administrators, so those fields never come from the request.
     */
    private static function ownBranchInput(array $input, int $districtId): array
    {
        unset($input['province_id'], $input['administrator_id']);
        $input['district_id'] = $districtId;
        return $input;
    }
}
