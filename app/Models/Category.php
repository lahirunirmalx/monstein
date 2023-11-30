<?php
namespace Monstein\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model {
    use SoftDeletes; // toggle soft deletes
    protected $table = 'categories';
    protected $fillable = ['user_id', 'name']; // for mass creation
    protected $hidden = ['deleted_at']; // hidden columns from select results
    protected $dates = ['deleted_at']; // the attributes that should be mutated to dates
    public static function boot() {
        parent::boot();
        // setup model event listeners
        static::setEventDispatcher(new \Illuminate\Events\Dispatcher());
        static::deleting(['\Monstein\Models\Events\Category', 'delete']); // DELETE event listener
    }
    public function user() {
        return $this->belongsTo('\Monstein\Models\User');
    }
    public function todos() {
        return $this->hasMany('\Monstein\Models\Todo', 'category_id');
    }
}