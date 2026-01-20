<?php
namespace Monstein\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Monstein\Config\Config;

/**
 * User model with authentication capabilities
 * 
 * @property int $id
 * @property string $username
 * @property string $password
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime|null $deleted_at
 */
class User extends Model
{
    use SoftDeletes;

    /** @var string */
    protected $table = 'users';

    /** @var array<string> */
    protected $fillable = ['username', 'password'];

    /** @var array<string> */
    protected $hidden = ['password', 'deleted_at'];

    /** @var array<string> */
    protected $dates = ['deleted_at'];

    /**
     * Get the categories for the user
     * 
     * @return HasMany
     */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'user_id');
    }

    /**
     * Get the todos for the user
     * 
     * @return HasMany
     */
    public function todos(): HasMany
    {
        return $this->hasMany(Todo::class, 'user_id');
    }

    /**
     * Hash the password before storing
     * 
     * @param string $pass Plain text password
     * @return void
     */
    public function setPasswordAttribute(string $pass): void
    {
        $this->attributes['password'] = password_hash($pass, Config::auth()['hash']);
    }

    /**
     * Create a JWT token for the user
     * 
     * @return array{token: string, expires: int}
     */
    public function tokenCreate(): array
    {
        $authConfig = Config::auth();
        $now = new \DateTime();
        $expires = new \DateTime('+' . $authConfig['expires'] . ' minutes');

        $payload = [
            'iat' => $now->getTimestamp(),      // Issued at
            'exp' => $expires->getTimestamp(),  // Expiration
            'sub' => $this->id,                  // Subject (user ID)
            'jti' => bin2hex(random_bytes(16))  // Unique token ID
        ];

        // firebase/php-jwt v6 requires explicit algorithm parameter
        $token = JWT::encode($payload, $authConfig['secret'], $authConfig['jwt']);

        return [
            'token' => $token,
            'expires' => $expires->getTimestamp()
        ];
    }

    /**
     * Verify a JWT token and return the decoded payload
     * 
     * @param string $token JWT token
     * @return object|null Decoded payload or null if invalid
     */
    public static function tokenVerify(string $token): ?object
    {
        try {
            $authConfig = Config::auth();
            // firebase/php-jwt v6.x requires Key object
            return JWT::decode($token, new Key($authConfig['secret'], $authConfig['jwt']));
        } catch (\Exception $e) {
            return null;
        }
    }
}
