<?php

namespace Ordinatrack\Api\Helpers;

use GUMP;
use Ordinatrack\Api\Config\Config;
use Exception;

/**
 * Validation Helper
 * 
 * Provides comprehensive input validation, file upload handling,
 * image processing, and utility functions using GUMP validation library
 */
class ValidationHelper
{
    /**
     * @var GUMP Instance of GUMP validator
     */
    private static ?GUMP $gump = null;

    /**
     * Allowed image MIME types
     */
    private const ALLOWED_IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg'
    ];

    /**
     * Allowed document MIME types
     */
    private const ALLOWED_DOCUMENT_TYPES = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'text/csv' => 'csv'
    ];

    /**
     * Maximum file sizes (in bytes)
     */
    private const MAX_FILE_SIZES = [
        'image' => 5242880,        // 5MB
        'document' => 10485760,    // 10MB
        'default' => 10485760     // 10MB
    ];

    /**
     * Get or initialize GUMP instance
     */
    private static function getGump(): GUMP
    {
        if (self::$gump === null) {
            self::$gump = new GUMP();
        }
        return self::$gump;
    }

    /**
     * Validate data against rules
     * 
     * @param array $data Data to validate
     * @param array $rules GUMP validation rules
     * 
     * @return array ['is_valid' => bool, 'errors' => array, 'data' => array]
     */
    public static function validate(array $data, array $rules): array
    {
        $gump = self::getGump();
        $sanitize = [];
        $datas = [];
        try {
            // Apply filters if provided
            foreach ($rules as $key => $value) {
                $datas[$key] = isset($data[$key]) ? $data[$key] : '';
                if (!is_array($datas[$key])) {
                    $sanitize[$key] = 'trim|sanitize_string';
                }
            }

            // Set validation rules
            $gump->validation_rules($rules);

            // Run validation
            $validated = $gump->run($datas);

            if ($validated === false) {
                return [
                    'is_valid' => false,
                    'errors' => $gump->errors(),
                    'data' => []
                ];
            }

            return [
                'is_valid' => true,
                'errors' => [],
                'data' => $validated
            ];
        } catch (Exception $e) {
            error_log("Validation error: " . $e->getMessage());
            return [
                'is_valid' => false,
                'errors' => ['validation' => 'Validation failed'],
                'data' => []
            ];
        }
    }

    /**
     * Common validation rule sets
     */
    public static function emailRules(): array
    {
        return [
            'email' => 'required|valid_email'
        ];
    }

    public static function passwordRules(): array
    {
        return [
            'password' => 'required|min_len,8|max_len,128'
        ];
    }

    public static function nameRules(): array
    {
        return [
            'first_name' => 'required|alpha_space|max_len,50',
            'last_name' => 'required|alpha_space|max_len,50'
        ];
    }

    public static function registrationRules(): array
    {
        return array_merge(
            self::emailRules(),
            self::passwordRules(),
            self::nameRules(),
            [
                'role' => 'required|alpha',
                'province_id' => 'integer',
                'district_id' => 'integer',
                'branch_name' => 'alpha_space|max_len,255'
            ]
        );
    }

    public static function loginRules(): array
    {
        return [
            'email' => 'required|valid_email',
            'password' => 'required'
        ];
    }

    public static function profileUpdateRules(): array
    {
        return [
            'first_name' => 'alpha_space|max_len,50',
            'last_name' => 'alpha_space|max_len,50',
            'email' => 'valid_email'
        ];
    }

    /**
     * Get allowed image types
     */
    public static function getAllowedImageTypes(): array
    {
        return self::ALLOWED_IMAGE_TYPES;
    }

    /**
     * Get allowed document types
     */
    public static function getAllowedDocumentTypes(): array
    {
        return self::ALLOWED_DOCUMENT_TYPES;
    }

    /**
     * Handle file upload
     * 
     * @param array $file $_FILES array element
     * @param string $uploadDir Directory to save file
     * @param string|null $allowedMimeTypes Comma-separated MIME types or 'image', 'document', 'all'
     * @param int|null $maxSize Maximum file size in bytes
     * @return array ['success' => bool, 'message' => string, 'file_path' => string|null, 'file_name' => string|null]
     */
    public static function uploadFile(
        array $file,
        string $uploadDir,
        string $allowedMimeTypes = 'all',
        ?int $maxSize = null
    ): array {
        try {
            // Validate file array
            if (!isset($file['tmp_name']) || !isset($file['name']) || !isset($file['error'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid file upload',
                    'file_path' => null,
                    'file_name' => null
                ];
            }

            // Check upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                return [
                    'success' => false,
                    'message' => self::getUploadErrorMessage($file['error']),
                    'file_path' => null,
                    'file_name' => null
                ];
            }

            // Validate file size
            $maxSize = $maxSize ?? self::MAX_FILE_SIZES['default'];
            if ($file['size'] > $maxSize) {
                return [
                    'success' => false,
                    'message' => 'File size exceeds maximum allowed (' . self::formatBytes($maxSize) . ')',
                    'file_path' => null,
                    'file_name' => null
                ];
            }

            // Get MIME type
            $mimeType = mime_content_type($file['tmp_name']);

            // Validate MIME type
            $allowedTypes = self::getAllowedTypesArray($allowedMimeTypes);
            if (!in_array($mimeType, array_keys($allowedTypes), true)) {
                return [
                    'success' => false,
                    'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes),
                    'file_path' => null,
                    'file_name' => null
                ];
            }

            // Create upload directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    error_log("Failed to create upload directory: {$uploadDir}");
                    return [
                        'success' => false,
                        'message' => 'Failed to create upload directory',
                        'file_path' => null,
                        'file_name' => null
                    ];
                }
            }

            // Generate unique filename
            $fileExtension = $allowedTypes[$mimeType];
            $fileName = bin2hex(random_bytes(16)) . '.' . $fileExtension;
            $filePath = rtrim($uploadDir, '/') . '/' . $fileName;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                error_log("Failed to move uploaded file to: {$filePath}");
                return [
                    'success' => false,
                    'message' => 'Failed to save file',
                    'file_path' => null,
                    'file_name' => null
                ];
            }

            // Set appropriate permissions
            chmod($filePath, 0644);

            error_log("File uploaded successfully: {$fileName}");

            return [
                'success' => true,
                'message' => 'File uploaded successfully',
                'file_path' => $filePath,
                'file_name' => $fileName
            ];
        } catch (Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'File upload failed',
                'file_path' => null,
                'file_name' => null
            ];
        }
    }

    /**
     * Handle image upload with validation
     * 
     * @param array $file $_FILES array element
     * @param string $uploadDir Directory to save image
     * @param int|null $maxWidth Maximum image width (optional)
     * @param int|null $maxHeight Maximum image height (optional)
     * @return array ['success' => bool, 'message' => string, 'file_path' => string|null, 'dimensions' => array|null]
     */
    public static function uploadImage(
        array $file,
        string $uploadDir,
        ?int $maxWidth = 4096,
        ?int $maxHeight = 4096
    ): array {
        try {
            // First, validate as a file
            $uploadResult = self::uploadFile(
                $file,
                $uploadDir,
                'image',
                self::MAX_FILE_SIZES['image']
            );

            if (!$uploadResult['success']) {
                return [
                    'success' => false,
                    'message' => $uploadResult['message'],
                    'file_path' => null,
                    'dimensions' => null
                ];
            }

            $filePath = $uploadResult['file_path'];

            // Validate image dimensions
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo === false) {
                unlink($filePath);
                return [
                    'success' => false,
                    'message' => 'Invalid image file',
                    'file_path' => null,
                    'dimensions' => null
                ];
            }

            [$width, $height] = $imageInfo;

            // Check dimensions
            if (($maxWidth && $width > $maxWidth) || ($maxHeight && $height > $maxHeight)) {
                unlink($filePath);
                return [
                    'success' => false,
                    'message' => "Image dimensions must not exceed {$maxWidth}x{$maxHeight}",
                    'file_path' => null,
                    'dimensions' => null
                ];
            }

            error_log("Image uploaded successfully: {$uploadResult['file_name']} ({$width}x{$height})");

            return [
                'success' => true,
                'message' => 'Image uploaded successfully',
                'file_path' => $filePath,
                'dimensions' => [
                    'width' => $width,
                    'height' => $height
                ]
            ];
        } catch (Exception $e) {
            error_log("Image upload error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Image upload failed',
                'file_path' => null,
                'dimensions' => null
            ];
        }
    }

    /**
     * Get file by name from directory
     * 
     * @param string $fileName File name to retrieve
     * @param string $directory Directory path
     * @return array ['success' => bool, 'message' => string, 'file_path' => string|null, 'mime_type' => string|null]
     */
    public static function getFile(string $fileName, string $directory): array
    {
        try {
            // Prevent path traversal attacks
            if (strpos($fileName, '..') !== false || strpos($fileName, '/') !== false) {
                return [
                    'success' => false,
                    'message' => 'Invalid file name',
                    'file_path' => null,
                    'mime_type' => null
                ];
            }

            $filePath = rtrim($directory, '/') . '/' . $fileName;

            // Verify file exists and is within the allowed directory
            $realPath = realpath($filePath);
            $realDir = realpath($directory);

            if (!$realPath || !$realDir || strpos($realPath, $realDir) !== 0) {
                return [
                    'success' => false,
                    'message' => 'File not found',
                    'file_path' => null,
                    'mime_type' => null
                ];
            }

            if (!file_exists($filePath) || !is_file($filePath)) {
                return [
                    'success' => false,
                    'message' => 'File not found',
                    'file_path' => null,
                    'mime_type' => null
                ];
            }

            $mimeType = mime_content_type($filePath);

            return [
                'success' => true,
                'message' => 'File found',
                'file_path' => $filePath,
                'mime_type' => $mimeType
            ];
        } catch (Exception $e) {
            error_log("Get file error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve file',
                'file_path' => null,
                'mime_type' => null
            ];
        }
    }

    /**
     * Get image by name from directory
     * 
     * @param string $imageName Image file name
     * @param string $directory Directory path
     * @return array ['success' => bool, 'message' => string, 'file_path' => string|null, 'dimensions' => array|null]
     */
    public static function getImage(string $imageName, string $directory): array
    {
        try {
            // Get file first
            $fileResult = self::getFile($imageName, $directory);

            if (!$fileResult['success']) {
                return [
                    'success' => false,
                    'message' => $fileResult['message'],
                    'file_path' => null,
                    'dimensions' => null
                ];
            }

            $filePath = $fileResult['file_path'];

            // Verify it's an image
            if (!in_array($fileResult['mime_type'], array_keys(self::ALLOWED_IMAGE_TYPES), true)) {
                return [
                    'success' => false,
                    'message' => 'File is not a valid image',
                    'file_path' => null,
                    'dimensions' => null
                ];
            }

            // Get image dimensions
            $imageInfo = @getimagesize($filePath);
            if ($imageInfo === false) {
                return [
                    'success' => false,
                    'message' => 'Invalid image file',
                    'file_path' => null,
                    'dimensions' => null
                ];
            }

            [$width, $height] = $imageInfo;

            return [
                'success' => true,
                'message' => 'Image found',
                'file_path' => $filePath,
                'dimensions' => [
                    'width' => $width,
                    'height' => $height,
                    'mime_type' => $fileResult['mime_type']
                ]
            ];
        } catch (Exception $e) {
            error_log("Get image error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve image',
                'file_path' => null,
                'dimensions' => null
            ];
        }
    }

    /**
     * Delete file
     * 
     * @param string $filePath Full file path
     * @return array ['success' => bool, 'message' => string]
     */
    public static function deleteFile(string $filePath): array
    {
        try {
            if (!file_exists($filePath)) {
                return [
                    'success' => false,
                    'message' => 'File not found'
                ];
            }

            if (!is_file($filePath)) {
                return [
                    'success' => false,
                    'message' => 'Path is not a file'
                ];
            }

            if (!unlink($filePath)) {
                error_log("Failed to delete file: {$filePath}");
                return [
                    'success' => false,
                    'message' => 'Failed to delete file'
                ];
            }

            error_log("File deleted successfully: {$filePath}");

            return [
                'success' => true,
                'message' => 'File deleted successfully'
            ];
        } catch (Exception $e) {
            error_log("Delete file error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to delete file'
            ];
        }
    }

    /**
     * Sanitize input string
     * 
     * @param string $input Input string
     * @param string $type Type of sanitization: 'email', 'url', 'string', 'html'
     * @return string Sanitized string
     */
    public static function sanitize(string $input, string $type = 'string'): string
    {
        return match ($type) {
            'email' => filter_var($input, FILTER_SANITIZE_EMAIL),
            'url' => filter_var($input, FILTER_SANITIZE_URL),
            'html' => htmlspecialchars($input, ENT_QUOTES, 'UTF-8'),
            'string' => trim(stripslashes($input)),
            default => trim(stripslashes($input))
        };
    }

    /**
     * Escape string for database
     * 
     * @param string $input Input string
     * @return string Escaped string
     */
    public static function escape(string $input): string
    {
        return addslashes($input);
    }

    /**
     * Check if input is a valid UUID
     * 
     * @param string $uuid UUID string
     * @return bool
     */
    public static function isValidUuid(string $uuid): bool
    {
        return preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $uuid
        ) === 1;
    }

    /**
     * Check if input is a valid phone number
     * 
     * @param string $phone Phone number
     * @return bool
     */
    public static function isValidPhone(string $phone): bool
    {
        return preg_match(
            '/^\+?[1-9]\d{1,14}$/',
            preg_replace('/\D/', '', $phone)
        ) === 1;
    }

    /**
     * Check if input is a strong password
     * 
     * Requires:
     * - At least 8 characters
     * - At least one uppercase letter
     * - At least one lowercase letter
     * - At least one number
     * - At least one special character
     * 
     * @param string $password Password to check
     * @return bool
     */
    public static function isStrongPassword(string $password): bool
    {
        return preg_match(
            '/^(?=.*[A-Z])(?=.*[a-z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]).{8,}$/',
            $password
        ) === 1;
    }

    /**
     * Get upload error message
     */
    private static function getUploadErrorMessage(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Temporary folder is missing',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'Extension stopped the file upload',
            default => 'Unknown upload error'
        };
    }

    /**
     * Get allowed types array
     */
    private static function getAllowedTypesArray(string $allowedMimeTypes): array
    {
        return match ($allowedMimeTypes) {
            'image' => self::ALLOWED_IMAGE_TYPES,
            'document' => self::ALLOWED_DOCUMENT_TYPES,
            'all' => array_merge(self::ALLOWED_IMAGE_TYPES, self::ALLOWED_DOCUMENT_TYPES),
            default => self::ALLOWED_IMAGE_TYPES
        };
    }

    /**
     * Format bytes to human readable format
     */
    private static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
