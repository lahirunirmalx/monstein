<?php
namespace Monstein\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Events\Dispatcher;

/**
 * Category model for organizing todos
 * 
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime|null $deleted_at
 */
class Category extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'categories';

    /** @var array<string> */
    protected $fillable = ['user_id', 'name'];

    /** @var array<string> */
    protected $hidden = ['deleted_at'];

    /** @var array<string> */
    protected $dates = ['deleted_at'];

    /**
     * Boot the model
     * 
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        // Setup model event listeners
        static::setEventDispatcher(new Dispatcher());

        // Cascade soft deletes to todos when category is deleted
        static::deleting(function (Category $category) {
            if (!$category->isForceDeleting()) {
                $category->todos()->delete();
            }
        });

        // Cascade force deletes to todos
        static::forceDeleting(function (Category $category) {
            $category->todos()->forceDelete();
        });
    }

    /**
     * Get the user that owns the category
     * 
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the todos for the category
     * 
     * @return HasMany
     */
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class, 'category_id');
    }
}
