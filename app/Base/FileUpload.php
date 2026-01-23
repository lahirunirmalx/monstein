<?php

namespace Monstein\Base;

use Psr\Http\Message\UploadedFileInterface;

/**
 * File Upload Utility
 * 
 * Handles file uploads from both multipart forms and base64 encoded data.
 * Supports file system storage and database storage (base64/blob).
 * 
 * Security features:
 * - File type validation (whitelist)
 * - File size limits
 * - Filename sanitization
 * - Directory traversal prevention
 * - MIME type validation
 * 
 * Supports PHP 7.4 and 8.x
 */
class FileUpload
{
    /** @var string Storage driver: 'filesystem', 'database', 'both' */
    private $driver;

    /** @var string Base path for file storage */
    private $storagePath;

    /** @var int Maximum file size in bytes */
    private $maxFileSize;

    /** @var array Allowed MIME types */
    private $allowedTypes;

    /** @var array Allowed file extensions */
    private $allowedExtensions;

    /** @var \Psr\Log\LoggerInterface|null */
    private $logger;

    /** @var array Default configuration */
    private static $defaults = [
        'driver' => 'filesystem',
        'storage_path' => null,
        'max_file_size' => 10485760, // 10MB
        'allowed_types' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'text/plain',
            'text/csv',
            'application/json',
        ],
        'allowed_extensions' => [
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'pdf', 'txt', 'csv', 'json'
        ],
    ];

    /**
     * @param array $options Configuration options
     */
    public function __construct(array $options = [])
    {
        $this->driver = $options['driver'] ?? self::$defaults['driver'];
        $this->storagePath = $options['storage_path'] ?? $this->getDefaultStoragePath();
        $this->maxFileSize = $options['max_file_size'] ?? self::$defaults['max_file_size'];
        $this->allowedTypes = $options['allowed_types'] ?? self::$defaults['allowed_types'];
        $this->allowedExtensions = $options['allowed_extensions'] ?? self::$defaults['allowed_extensions'];
        $this->logger = $options['logger'] ?? null;

        // Ensure storage directory exists
        if ($this->driver !== 'database' && !is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Get default storage path
     * 
     * @return string
     */
    private function getDefaultStoragePath(): string
    {
        $envPath = $_ENV['FILE_STORAGE_PATH'] ?? '';
        if (!empty($envPath)) {
            return $envPath[0] === '/' ? $envPath : dirname(__DIR__, 2) . '/' . $envPath;
        }
        return dirname(__DIR__, 2) . '/storage/uploads';
    }

    /**
     * Handle file upload from PSR-7 UploadedFileInterface (multipart form)
     * 
     * @param UploadedFileInterface $file
     * @param array $options Override options for this upload
     * @return array Result with file info or error
     */
    public function handleUpload(UploadedFileInterface $file, array $options = []): array
    {
        // Check for upload errors
        if ($file->getError() !== UPLOAD_ERR_OK) {
            return $this->error($this->getUploadErrorMessage($file->getError()));
        }

        // Get file info
        $originalName = $file->getClientFilename();
        $mimeType = $file->getClientMediaType();
        $size = $file->getSize();

        // Validate
        $validation = $this->validate($originalName, $mimeType, $size, $options);
        if (!$validation['valid']) {
            return $this->error($validation['error']);
        }

        // Get file content
        $stream = $file->getStream();
        $content = $stream->getContents();
        $stream->rewind();

        // Verify MIME type from content (not just header)
        $actualMime = $this->detectMimeType($content);
        if ($actualMime !== null && !in_array($actualMime, $this->allowedTypes, true)) {
            return $this->error('File content does not match allowed types');
        }

        return $this->store($content, $originalName, $mimeType, $size, $options);
    }

    /**
     * Handle base64 encoded file upload
     * 
     * @param string $base64Data Base64 encoded file data (with or without data URI prefix)
     * @param string $filename Original filename
     * @param array $options Override options for this upload
     * @return array Result with file info or error
     */
    public function handleBase64(string $base64Data, string $filename, array $options = []): array
    {
        // Parse data URI if present (data:image/png;base64,...)
        $mimeType = null;
        if (preg_match('/^data:([a-zA-Z0-9\/\-\+\.]+);base64,/', $base64Data, $matches)) {
            $mimeType = $matches[1];
            $base64Data = substr($base64Data, strlen($matches[0]));
        }

        // Decode base64
        $content = base64_decode($base64Data, true);
        if ($content === false) {
            return $this->error('Invalid base64 encoding');
        }

        $size = strlen($content);

        // Detect MIME type from content if not provided
        if ($mimeType === null) {
            $mimeType = $this->detectMimeType($content);
        }

        if ($mimeType === null) {
            $mimeType = 'application/octet-stream';
        }

        // Validate
        $validation = $this->validate($filename, $mimeType, $size, $options);
        if (!$validation['valid']) {
            return $this->error($validation['error']);
        }

        return $this->store($content, $filename, $mimeType, $size, $options);
    }

    /**
     * Store file based on configured driver
     * 
     * @param string $content File content
     * @param string $originalName Original filename
     * @param string $mimeType MIME type
     * @param int $size File size in bytes
     * @param array $options Additional options
     * @return array Result with file info
     */
    private function store(string $content, string $originalName, string $mimeType, int $size, array $options): array
    {
        $driver = $options['driver'] ?? $this->driver;
        $result = [
            'success' => true,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size' => $size,
            'hash' => hash('sha256', $content),
        ];

        // Generate unique filename
        $extension = $this->getExtension($originalName, $mimeType);
        $storedName = $this->generateFilename($extension);

        if ($driver === 'filesystem' || $driver === 'both') {
            $filePath = $this->storeToFilesystem($content, $storedName, $options);
            if ($filePath === null) {
                return $this->error('Failed to store file to filesystem');
            }
            $result['path'] = $filePath;
            $result['stored_name'] = $storedName;
        }

        if ($driver === 'database' || $driver === 'both') {
            $dbFormat = $options['db_format'] ?? 'base64'; // 'base64' or 'blob'
            $result['db_data'] = $this->prepareForDatabase($content, $dbFormat);
            $result['db_format'] = $dbFormat;
        }

        $this->log('info', 'File uploaded successfully', [
            'original_name' => $originalName,
            'stored_name' => $storedName ?? null,
            'size' => $size,
            'driver' => $driver,
        ]);

        return $result;
    }

    /**
     * Store file to filesystem
     * 
     * @param string $content
     * @param string $filename
     * @param array $options
     * @return string|null File path or null on failure
     */
    private function storeToFilesystem(string $content, string $filename, array $options): ?string
    {
        $path = $options['storage_path'] ?? $this->storagePath;
        
        // Create subdirectory based on date (organizes files)
        $subDir = date('Y/m');
        $fullPath = $path . '/' . $subDir;
        
        if (!is_dir($fullPath)) {
            if (!mkdir($fullPath, 0755, true)) {
                $this->log('error', 'Failed to create directory', ['path' => $fullPath]);
                return null;
            }
        }

        $filePath = $fullPath . '/' . $filename;
        
        if (file_put_contents($filePath, $content, LOCK_EX) === false) {
            $this->log('error', 'Failed to write file', ['path' => $filePath]);
            return null;
        }

        // Return relative path from storage root
        return $subDir . '/' . $filename;
    }

    /**
     * Prepare file content for database storage
     * 
     * @param string $content
     * @param string $format 'base64' or 'blob'
     * @return string
     */
    private function prepareForDatabase(string $content, string $format): string
    {
        if ($format === 'base64') {
            return base64_encode($content);
        }
        // For blob, return raw content (Eloquent will handle escaping)
        return $content;
    }

    /**
     * Retrieve file from database storage
     * 
     * @param string $data Database stored data
     * @param string $format 'base64' or 'blob'
     * @return string Raw file content
     */
    public function retrieveFromDatabase(string $data, string $format): string
    {
        if ($format === 'base64') {
            return base64_decode($data);
        }
        return $data;
    }

    /**
     * Validate file
     * 
     * @param string $filename
     * @param string $mimeType
     * @param int $size
     * @param array $options
     * @return array ['valid' => bool, 'error' => string|null]
     */
    private function validate(string $filename, string $mimeType, int $size, array $options): array
    {
        $maxSize = $options['max_file_size'] ?? $this->maxFileSize;
        $allowedTypes = $options['allowed_types'] ?? $this->allowedTypes;
        $allowedExtensions = $options['allowed_extensions'] ?? $this->allowedExtensions;

        // Check file size
        if ($size > $maxSize) {
            return ['valid' => false, 'error' => 'File size exceeds limit of ' . $this->formatBytes($maxSize)];
        }

        if ($size === 0) {
            return ['valid' => false, 'error' => 'File is empty'];
        }

        // Check MIME type
        if (!in_array($mimeType, $allowedTypes, true)) {
            return ['valid' => false, 'error' => 'File type not allowed: ' . $mimeType];
        }

        // Check extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true)) {
            return ['valid' => false, 'error' => 'File extension not allowed: ' . $extension];
        }

        // Check for dangerous filenames
        $sanitized = SecurityUtils::sanitizeFilename($filename);
        if (empty($sanitized) || $sanitized === 'unnamed') {
            return ['valid' => false, 'error' => 'Invalid filename'];
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Detect MIME type from file content
     * 
     * @param string $content
     * @return string|null
     */
    private function detectMimeType(string $content): ?string
    {
        // Use finfo if available
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($content);
            if ($mime !== false) {
                return $mime;
            }
        }

        // Fallback: check magic bytes
        $magicBytes = [
            "\xFF\xD8\xFF" => 'image/jpeg',
            "\x89PNG\r\n\x1a\n" => 'image/png',
            "GIF87a" => 'image/gif',
            "GIF89a" => 'image/gif',
            "RIFF" => 'image/webp', // Simplified check
            "%PDF" => 'application/pdf',
            "PK\x03\x04" => 'application/zip',
        ];

        foreach ($magicBytes as $magic => $mime) {
            if (strpos($content, $magic) === 0) {
                return $mime;
            }
        }

        return null;
    }

    /**
     * Generate unique filename
     * 
     * @param string $extension
     * @return string
     */
    private function generateFilename(string $extension): string
    {
        return bin2hex(random_bytes(16)) . '.' . $extension;
    }

    /**
     * Get file extension from filename or MIME type
     * 
     * @param string $filename
     * @param string $mimeType
     * @return string
     */
    private function getExtension(string $filename, string $mimeType): string
    {
        // Try from filename first
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!empty($ext) && in_array($ext, $this->allowedExtensions, true)) {
            return $ext;
        }

        // Fallback to MIME type
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            'application/json' => 'json',
        ];

        return $mimeToExt[$mimeType] ?? 'bin';
    }

    /**
     * Delete file from filesystem
     * 
     * @param string $path Relative path from storage root
     * @return bool
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->storagePath . '/' . $path;
        
        // Prevent directory traversal
        $realPath = realpath($fullPath);
        $realStoragePath = realpath($this->storagePath);
        
        if ($realPath === false || strpos($realPath, $realStoragePath) !== 0) {
            $this->log('warning', 'Attempted to delete file outside storage', ['path' => $path]);
            return false;
        }

        if (file_exists($realPath) && is_file($realPath)) {
            return unlink($realPath);
        }

        return false;
    }

    /**
     * Get file content from filesystem
     * 
     * @param string $path Relative path from storage root
     * @return string|null
     */
    public function get(string $path): ?string
    {
        $fullPath = $this->storagePath . '/' . $path;
        
        // Prevent directory traversal
        $realPath = realpath($fullPath);
        $realStoragePath = realpath($this->storagePath);
        
        if ($realPath === false || strpos($realPath, $realStoragePath) !== 0) {
            return null;
        }

        if (file_exists($realPath) && is_file($realPath)) {
            return file_get_contents($realPath);
        }

        return null;
    }

    /**
     * Get URL for file (if public)
     * 
     * @param string $path
     * @return string
     */
    public function getUrl(string $path): string
    {
        $baseUrl = $_ENV['FILE_BASE_URL'] ?? '/uploads';
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * Format bytes to human readable
     * 
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get upload error message
     * 
     * @param int $errorCode
     * @return string
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload',
        ];

        return $messages[$errorCode] ?? 'Unknown upload error';
    }

    /**
     * Create error result
     * 
     * @param string $message
     * @return array
     */
    private function error(string $message): array
    {
        $this->log('warning', 'File upload failed', ['error' => $message]);
        return [
            'success' => false,
            'error' => $message,
        ];
    }

    /**
     * Log message
     * 
     * @param string $level
     * @param string $message
     * @param array $context
     */
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->$level('[FileUpload] ' . $message, $context);
        }
    }

    /**
     * Get configuration for specific allowed types presets
     * 
     * @param string $preset 'images', 'documents', 'all'
     * @return array
     */
    public static function getPreset(string $preset): array
    {
        $presets = [
            'images' => [
                'allowed_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            ],
            'documents' => [
                'allowed_types' => ['application/pdf', 'text/plain', 'text/csv', 'application/json'],
                'allowed_extensions' => ['pdf', 'txt', 'csv', 'json'],
            ],
            'all' => self::$defaults,
        ];

        return $presets[$preset] ?? self::$defaults;
    }
}
