<?php
namespace Monstein\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Todo item model
 * 
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property string $name
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime|null $deleted_at
 */
class Todo extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'todo';

    /** @var array<string> */
    protected $fillable = ['user_id', 'category_id', 'name'];

    /** @var array<string> */
    protected $hidden = ['deleted_at'];

    /** @var array<string> */
    protected $dates = ['deleted_at'];

    /**
     * Get the user that owns the todo
     * 
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the category of the todo
     * 
     * @return BelongsTo
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
