<?php

namespace Ordinatrack\Api\Helpers;

/**
 * LocationValidator
 *
 * Shared input validation for provinces, districts and branches, used both by the super
 * admin Locations screens and by district leads managing their own branches, so the same
 * rules apply wherever a location is created.
 *
 * Returns [input, null] on success or [null, errorResponse] with a 422 status.
 */
class LocationValidator
{
    /**
     * Location names: letters, digits, spaces and . / ' & ( ) - (e.g. "OGBA/EGBEMA").
     * Same rule as registration's ORG_NAME_PATTERN in ValidationHelper, so any name
     * created here can also be submitted at signup.
     */
    public const NAME_PATTERN = '/^[\p{L}\p{N} .\/\'&()-]+$/u';

    /** Readable messages for the GUMP rules used here (%s = rule parameter) */
    private const RULE_MESSAGES = [
        'required' => 'is required',
        'valid_email' => 'must be a valid email address',
        'max_len' => 'must be at most %s characters',
        'alpha_numeric_dash' => 'may only contain letters, numbers, dashes and underscores',
        'regex' => "may only contain letters, numbers, spaces and . / ' & ( ) -",
    ];

    /**
     * Validate location input: text fields through GUMP, ids and the active flag here.
     * On update only the fields present in the request are validated.
     *
     * @param string $type provinces|districts|branches
     * @param bool $creating False for partial updates
     */
    public static function input(array $data, string $type, bool $creating): array
    {
        $rules = [
            'name' => ['required', 'regex' => [self::NAME_PATTERN], 'max_len' => 100],
            'code' => 'alpha_numeric_dash|max_len,10',
            'description' => 'max_len,1000',
        ];
        if ($type === 'branches') {
            $rules += [
                'address' => 'max_len,500',
                'contact_person' => 'max_len,100',
                'contact_phone' => 'max_len,20',
                'contact_email' => 'valid_email|max_len,255',
            ];
        }
        if (!$creating) {
            $rules = array_intersect_key($rules, $data);
        }

        $input = [];
        if ($rules) {
            $validation = ValidationHelper::validate($data, $rules);
            if (!$validation['is_valid']) {
                return [null, self::validationError($validation['errors'])];
            }
            foreach ($validation['data'] as $key => $value) {
                $input[$key] = is_string($value) ? trim($value) : $value;
            }
        }

        foreach (['province_id', 'district_id', 'administrator_id'] as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if ($value !== null && $value !== '' && !is_numeric($value)) {
                $label = ucfirst(str_replace('_', ' ', $key));
                return [null, ['success' => false, 'message' => "$label must be a numeric id", 'status' => 422]];
            }
            $input[$key] = ($value === null || $value === '') ? null : (int)$value;
        }

        if (array_key_exists('is_active', $data)) {
            $active = filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active === null) {
                return [null, ['success' => false, 'message' => 'Status must be active or inactive', 'status' => 422]];
            }
            $input['is_active'] = $active;
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
            $template = self::RULE_MESSAGES[$error['rule'] ?? ''] ?? 'is invalid';
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' ' . vsprintf($template, (array)($error['params'] ?? []));
        }

        return ['success' => false, 'message' => implode('. ', $errors), 'errors' => $errors, 'status' => 422];
    }
}
