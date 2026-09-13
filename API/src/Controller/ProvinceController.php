<?php

namespace Ordinatrack\Api\Controller;

use Ordinatrack\Api\Helpers\LocationValidator;
use Ordinatrack\Api\Model\LocationModel;
use Ordinatrack\Api\Model\ProvinceModel;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * ProvinceController
 *
 * Endpoints for a province lead's own province. Every handler first resolves the province
 * the signed-in user administers, so one province can never read or change another
 * province's districts, branches or members.
 *
 * Districts are written through LocationModel (the same path the super admin uses), with
 * the province forced to the lead's own and the district administrator left alone, since
 * assigning administrators stays a super admin action. Branches and members are
 * read-only: district leads and branch administrators manage those.
 *
 * Handlers receive the authenticated user (object), the request data (array) and, for
 * /districts/{id} routes, the numeric id. A 'status' key in the returned array is the HTTP
 * status code; the route strips it before encoding.
 */
class ProvinceController
{
    // ==================== PROVINCE ====================

    /**
     * GET /province/info - The signed-in user's province and its headline numbers
     */
    public static function getProvince($user, array $data): array
    {
        return self::withProvince($user, fn(array $province) => [
            'success' => true,
            'data' => [
                'id' => (int)$province['id'],
                'name' => $province['name'],
                'code' => $province['code'],
                'stats' => ProvinceModel::stats((int)$province['id']),
            ],
        ]);
    }

    /**
     * GET /province/activity?limit= - What the province's administrators have been doing
     */
    public static function getActivity($user, array $data): array
    {
        return self::withProvince($user, function (array $province) use ($data) {
            $activity = ProvinceModel::getActivity((int)$province['id'], (int)($data['limit'] ?? 20));
            return ['success' => true, 'data' => $activity, 'count' => count($activity)];
        });
    }

    /**
     * GET /province/breakdown - Per-district rollup for the reports page
     */
    public static function getBreakdown($user, array $data): array
    {
        return self::withProvince($user, function (array $province) {
            $rows = ProvinceModel::breakdown((int)$province['id']);
            return ['success' => true, 'data' => $rows, 'count' => count($rows)];
        });
    }

    // ==================== DISTRICTS ====================

    /**
     * GET /province/districts?search=&status=&limit=&offset=
     */
    public static function getDistricts($user, array $data): array
    {
        return self::withProvince($user, function (array $province) use ($data) {
            $result = ProvinceModel::getDistricts(
                (int)$province['id'],
                $data,
                (int)($data['limit'] ?? 20),
                (int)($data['offset'] ?? 0)
            );
            return ['success' => true, 'data' => $result['districts'], 'total' => $result['total']];
        });
    }

    /**
     * GET /province/districts/{id}
     */
    public static function getDistrict($user, array $data, int $id): array
    {
        return self::withProvince($user, function (array $province) use ($id) {
            $district = ProvinceModel::getDistrict((int)$province['id'], $id);
            if (!$district) {
                return ['success' => false, 'message' => 'District not found in your province', 'status' => 404];
            }
            return ['success' => true, 'data' => $district];
        });
    }

    /**
     * POST /province/districts - {name, code?, description?, is_active?}
     */
    public static function createDistrict($user, array $data): array
    {
        return self::withProvince($user, function (array $province) use ($data) {
            [$input, $error] = LocationValidator::input($data, 'districts', true);
            if ($error) {
                return $error;
            }

            $created = LocationModel::create('districts', self::ownDistrictInput($input, (int)$province['id']));
            $district = ProvinceModel::getDistrict((int)$province['id'], (int)$created['id']);

            return ['success' => true, 'message' => 'District created', 'data' => $district, 'status' => 201];
        });
    }

    /**
     * PUT /province/districts/{id} - Partial update
     */
    public static function updateDistrict($user, array $data, int $id): array
    {
        return self::withProvince($user, function (array $province) use ($data, $id) {
            ProvinceModel::requireDistrict((int)$province['id'], $id);

            [$input, $error] = LocationValidator::input($data, 'districts', false);
            if ($error) {
                return $error;
            }

            LocationModel::update('districts', $id, self::ownDistrictInput($input, (int)$province['id']));
            $district = ProvinceModel::getDistrict((int)$province['id'], $id);

            return ['success' => true, 'message' => 'District updated', 'data' => $district];
        });
    }

    /**
     * DELETE /province/districts/{id} - Refused while the district still has branches
     */
    public static function deleteDistrict($user, array $data, int $id): array
    {
        return self::withProvince($user, function (array $province) use ($id) {
            ProvinceModel::deleteDistrict((int)$province['id'], $id);
            return ['success' => true, 'message' => 'District deleted'];
        });
    }

    // ==================== BRANCHES / MEMBERS (read-only) ====================

    /**
     * GET /province/branches?district_id=&search=&status=&limit=&offset=
     *
     * Read-only: branches are created and edited by their district lead.
     */
    public static function getBranches($user, array $data): array
    {
        return self::withProvince($user, function (array $province) use ($data) {
            $result = ProvinceModel::getBranches(
                (int)$province['id'],
                $data,
                (int)($data['limit'] ?? 20),
                (int)($data['offset'] ?? 0)
            );
            return ['success' => true, 'data' => $result['branches'], 'total' => $result['total']];
        });
    }

    /**
     * GET /province/members?district_id=&branch_id=&role=&status=&search=&limit=&offset=
     *
     * Read-only: members are added and edited by their own branch administrator.
     */
    public static function getMembers($user, array $data): array
    {
        return self::withProvince($user, function (array $province) use ($data) {
            $result = ProvinceModel::getMembers(
                (int)$province['id'],
                $data,
                (int)($data['limit'] ?? 20),
                (int)($data['offset'] ?? 0)
            );
            return ['success' => true, 'data' => $result['members'], 'total' => $result['total']];
        });
    }

    // ==================== HELPERS ====================

    /**
     * Resolve the user's province and run the handler with it, mapping model exceptions
     * to error responses. Users who don't administer a province get a NO_PROVINCE error.
     */
    private static function withProvince($user, callable $action): array
    {
        try {
            $province = ProvinceModel::provinceFor((int)$user->id);
            if (!$province) {
                return [
                    'success' => false,
                    'code' => 'NO_PROVINCE',
                    'message' => 'Ask your super admin to open Locations, find your province and set you as its '
                        . "Administrator. You'll be able to manage its districts here once that's done.",
                    'status' => 409,
                ];
            }
            return $action($province);
        } catch (PDOException $e) {
            error_log('Province database error: ' . $e->getMessage());
        } catch (RuntimeException $e) {
            // Business-rule failures carry their HTTP status (4xx) as the exception code
            $status = (int)$e->getCode();
            if ($status >= 400 && $status < 500) {
                return ['success' => false, 'message' => $e->getMessage(), 'status' => $status];
            }
            error_log('Province error: ' . $e->getMessage());
        } catch (Throwable $e) {
            error_log("Province error: {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}");
        }

        return ['success' => false, 'message' => 'An unexpected error occurred', 'status' => 500];
    }

    /**
     * Districts always belong to the lead's own province, and only a super admin
     * assigns district administrators, so those fields never come from the request.
     */
    private static function ownDistrictInput(array $input, int $provinceId): array
    {
        unset($input['district_id'], $input['administrator_id']);
        $input['province_id'] = $provinceId;
        return $input;
    }
}
