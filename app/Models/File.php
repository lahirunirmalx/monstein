<?php

namespace Monstein\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * File model for database storage of uploaded files
 * 
 * Supports both base64 and blob storage formats.
 * 
 * @property int $id
 * @property int $user_id
 * @property string $original_name
 * @property string $stored_name
 * @property string $path
 * @property string $mime_type
 * @property int $size
 * @property string $hash
 * @property string $storage_type 'filesystem', 'database', 'both'
 * @property string $db_format 'base64' or 'blob'
 * @property string|null $content File content (for database storage)
 * @property array|null $metadata Additional metadata
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime|null $deleted_at
 */
class File extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'files';

    /** @var array<string> */
    protected $fillable = [
        'user_id',
        'original_name',
        'stored_name',
        'path',
        'mime_type',
        'size',
        'hash',
        'storage_type',
        'db_format',
        'content',
        'metadata',
    ];

    /** @var array<string> */
    protected $hidden = ['content', 'deleted_at'];

    /** @var array<string> */
    protected $dates = ['deleted_at'];

    /** @var array<string, string> */
    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
    ];

    /**
     * Get the user that owns the file
     * 
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Create a new file record from upload result
     * 
     * @param array $uploadResult Result from FileUpload::handleUpload or handleBase64
     * @param int $userId
     * @param array $metadata Additional metadata
     * @return static|null
     */
    public static function createFromUpload(array $uploadResult, int $userId, array $metadata = []): ?self
    {
        if (!$uploadResult['success']) {
            return null;
        }

        $file = new static();
        $file->user_id = $userId;
        $file->original_name = $uploadResult['original_name'];
        $file->mime_type = $uploadResult['mime_type'];
        $file->size = $uploadResult['size'];
        $file->hash = $uploadResult['hash'];
        
        // Set storage type
        if (isset($uploadResult['path']) && isset($uploadResult['db_data'])) {
            $file->storage_type = 'both';
        } elseif (isset($uploadResult['db_data'])) {
            $file->storage_type = 'database';
        } else {
            $file->storage_type = 'filesystem';
        }

        // Filesystem storage info
        if (isset($uploadResult['path'])) {
            $file->path = $uploadResult['path'];
            $file->stored_name = $uploadResult['stored_name'];
        }

        // Database storage
        if (isset($uploadResult['db_data'])) {
            $file->content = $uploadResult['db_data'];
            $file->db_format = $uploadResult['db_format'];
        }

        $file->metadata = $metadata;
        $file->save();

        return $file;
    }

    /**
     * Get file content
     * 
     * For database storage, returns the stored content.
     * For filesystem storage, reads from disk.
     * 
     * @return string|null
     */
    public function getContent(): ?string
    {
        if ($this->storage_type === 'database' || $this->storage_type === 'both') {
            if ($this->db_format === 'base64') {
                return base64_decode($this->content);
            }
            return $this->content;
        }

        // Filesystem storage
        if ($this->path) {
            $fileUpload = new \Monstein\Base\FileUpload();
            return $fileUpload->get($this->path);
        }

        return null;
    }

    /**
     * Get base64 encoded content (useful for API responses)
     * 
     * @return string|null
     */
    public function getBase64Content(): ?string
    {
        if ($this->storage_type === 'database' || $this->storage_type === 'both') {
            if ($this->db_format === 'base64') {
                return $this->content;
            }
            return base64_encode($this->content);
        }

        $content = $this->getContent();
        return $content ? base64_encode($content) : null;
    }

    /**
     * Get data URI for the file
     * 
     * @return string|null
     */
    public function getDataUri(): ?string
    {
        $base64 = $this->getBase64Content();
        if ($base64 === null) {
            return null;
        }

        return 'data:' . $this->mime_type . ';base64,' . $base64;
    }

    /**
     * Get public URL for the file (filesystem storage only)
     * 
     * @return string|null
     */
    public function getUrl(): ?string
    {
        if ($this->path && ($this->storage_type === 'filesystem' || $this->storage_type === 'both')) {
            $fileUpload = new \Monstein\Base\FileUpload();
            return $fileUpload->getUrl($this->path);
        }

        return null;
    }

    /**
     * Delete file from storage
     * 
     * Deletes from both filesystem and database if applicable.
     * 
     * @return bool
     */
    public function deleteFromStorage(): bool
    {
        // Delete from filesystem if applicable
        if ($this->path && ($this->storage_type === 'filesystem' || $this->storage_type === 'both')) {
            $fileUpload = new \Monstein\Base\FileUpload();
            $fileUpload->delete($this->path);
        }

        // Clear database content
        if ($this->storage_type === 'database' || $this->storage_type === 'both') {
            $this->content = null;
            $this->save();
        }

        return true;
    }

    /**
     * Format file size for display
     * 
     * @return string
     */
    public function getFormattedSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Check if file is an image
     * 
     * @return bool
     */
    public function isImage(): bool
    {
        return strpos($this->mime_type, 'image/') === 0;
    }

    /**
     * Scope to user
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to images only
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeImages($query)
    {
        return $query->where('mime_type', 'LIKE', 'image/%');
    }

    /**
     * Convert to array for API response
     * 
     * @return array
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_formatted' => $this->getFormattedSize(),
            'url' => $this->getUrl(),
            'is_image' => $this->isImage(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
