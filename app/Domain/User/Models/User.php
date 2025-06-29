<?php

namespace App\Domain\User\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\User\ValueObjects\Email;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\DatabaseNotificationCollection;
use Illuminate\Notifications\Notifiable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property mixed $password
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read DatabaseNotificationCollection<int,DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 *
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return \Database\Factories\UserFactory::new();
    }

    /**
     * The Email value object
     */
    private ?Email $emailObject = null;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the Email value object
     */
    public function getEmailValueObject(): Email
    {
        if ($this->emailObject === null) {
            $this->emailObject = Email::fromArray([
                'email' => $this->attributes['email'],
                'email_verified_at' => $this->email_verified_at,
            ]);
        }

        return $this->emailObject;
    }

    /**
     * Get the email address
     */
    public function getEmailAttribute(): string
    {
        return $this->getEmailValueObject()->address;
    }

    /**
     * Set the email address
     */
    public function setEmailAttribute(string $value): void
    {
        $verifiedAt = $this->email_verified_at;
        $this->emailObject = new Email($value, $verifiedAt instanceof CarbonImmutable
            ? $verifiedAt
            : ($verifiedAt ? $verifiedAt->toImmutable() : null));
        $this->attributes['email'] = $value;
    }

    /**
     * Get the email verified at timestamp
     */
    public function getEmailVerifiedAtAttribute($value)
    {
        return $value;
    }

    /**
     * Set the email verified at timestamp
     */
    public function setEmailVerifiedAtAttribute($value): void
    {
        $this->attributes['email_verified_at'] = $value;

        if ($this->emailObject !== null) {
            $this->emailObject = new Email(
                $this->attributes['email'],
                $value instanceof CarbonImmutable
                    ? $value
                    : ($value ? CarbonImmutable::parse($value) : null)
            );
        }
    }
}
