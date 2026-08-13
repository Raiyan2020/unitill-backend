<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\StudentVerificationPeriod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'email',
        'phone',
        'image',
        'password',
        'address',
        'status',
        'activation_code',
        'resend_code_count',
        'device_token',
        'device_type',
        'remember_token',
        'city_id',
        'country_code',
        'password_updated_at',
        'privacy_settings_enabled',
        'show_last_name',
        'show_approximate_location',
        'trusted_users_only',
        'notify_system',
        'notify_chat',
        'notify_ads',
        'student_email',
        'student_verified_at',
        'student_reverify_due_at',
        'reverify_notified_at',
        'activation_code_expires_at',
        'activation_sent_at',
        'terms_accepted_at',
        'login_otp',
        'login_otp_expires_at',
        'login_otp_sent_at',
        ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'activation_code',
        'login_otp',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */


    public static $rules = [
        'first_name' => 'required|string|max:255',
        'last_name' => 'required|string|max:255',
        'phone' => 'required|max:191',
        'email' => 'nullable|email|max:255|unique:users,email',
        'password' => 'required|string|min:6|confirmed',
//        'country_code' => 'required|string|max:5',

    ];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'password_updated_at' => 'datetime',
        'privacy_settings_enabled' => 'boolean',
        'show_last_name' => 'boolean',
        'show_approximate_location' => 'boolean',
        'trusted_users_only' => 'boolean',
        'notify_system' => 'boolean',
        'notify_chat' => 'boolean',
        'notify_ads' => 'boolean',
        'is_trusted_seller' => 'boolean',
        'trusted_seller_verified_at' => 'datetime',
        'activation_code_expires_at' => 'datetime',
        'activation_sent_at' => 'datetime',
        'terms_accepted_at' => 'datetime',
        'reset_code_expire' => 'datetime',
        'login_otp_expires_at' => 'datetime',
        'login_otp_sent_at' => 'datetime',
        'student_verified_at' => 'datetime',
        'student_reverify_due_at' => 'datetime',
        'reverify_notified_at' => 'datetime',
    ];

    /** Whether the annual verification date has arrived. */
    public function needsReverification(): bool
    {
        return $this->student_reverify_due_at !== null
            && now()->greaterThanOrEqualTo($this->student_reverify_due_at);
    }

    /** The end of the 60-day period in which the user may still use the app. */
    public function reverificationGraceEndsAt(): ?\Carbon\Carbon
    {
        if ($this->student_reverify_due_at === null) {
            return null;
        }

        return StudentVerificationPeriod::graceEndsAt($this->student_reverify_due_at);
    }

    public function isInReverificationGracePeriod(): bool
    {
        return $this->needsReverification() && ! $this->isReverificationBlocked();
    }

    /** Whether the annual due date and the complete grace period have passed. */
    public function isReverificationBlocked(): bool
    {
        $graceEndsAt = $this->reverificationGraceEndsAt();

        return $graceEndsAt !== null && now()->greaterThanOrEqualTo($graceEndsAt);
    }

    protected static function booted(): void
    {
        static::saving(function (User $user) {
            if ($user->isDirty('password')) {
                $user->password_updated_at = now();
            }
        });
    }

    //city
    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }



    //notifications
    public function notifications()
    {
        return $this->hasMany(UserNotification::class);
    }

    public function userDevices()
    {
        return $this->hasMany(UserDevice::class);
    }

    public function loginLogs()
    {
        return $this->hasMany(UserLoginLog::class);
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function biometricTokens()
    {
        return $this->hasMany(BiometricToken::class);
    }

    public function contactUsMessages()
    {
        return $this->hasMany(ContactUsMessage::class);
    }

    public function ads()
    {
        return $this->hasMany(Ad::class);
    }

    public function favoriteAds()
    {
        return $this->belongsToMany(Ad::class, 'ad_favorites')->withTimestamps();
    }

    public function trustedSellerApplications()
    {
        return $this->hasMany(TrustedSellerApplication::class);
    }

    public function latestTrustedSellerApplication()
    {
        return $this->hasOne(TrustedSellerApplication::class)->latestOfMany();
    }

    /**
     * The most recent APPROVED trusted-seller application, used to surface the
     * seller's external contact details (WhatsApp / phone / email) on listings.
     */
    public function latestApprovedTrustedSellerApplication()
    {
        return $this->hasOne(TrustedSellerApplication::class)
            ->where('status', 'approved')
            ->latestOfMany();
    }

    public function ratingsGiven()
    {
        return $this->hasMany(UserRating::class, 'rater_id');
    }

    public function ratingsReceived()
    {
        return $this->hasMany(UserRating::class, 'rated_user_id');
    }

    public function buyerConversations()
    {
        return $this->hasMany(Conversation::class, 'buyer_id');
    }

    public function sellerConversations()
    {
        return $this->hasMany(Conversation::class, 'seller_id');
    }

    public function averageRatingReceived(): float
    {
        return (float) ($this->ratingsReceived()->avg('score') ?? 0);
    }

}
