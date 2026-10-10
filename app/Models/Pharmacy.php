<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * A branch: one physical shop. (The table is still called `pharmacies`
 * because every pharmacy that existed before branches became the first
 * branch of its own organization.) Stock, sales and online orders
 * are all recorded against a branch.
 */
class Pharmacy extends Model
{
    protected $fillable = [
        'user_id',
        'organization_id',
        'is_main',
        'is_active',
        'name',
        'address',
        'city',
        'state',
        'phone',
        'email',
        'latitude',
        'longitude',
        'type',
        'operating_hours',
    ];

    protected $casts = [
        'is_main' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        // A branch always belongs to an organization. The first pharmacy an
        // owner registers creates theirs and becomes its main branch.
        static::creating(function (Pharmacy $branch) {
            if (! $branch->organization_id) {
                $organization = Organization::firstOrCreate(['owner_id' => $branch->user_id], ['name' => $branch->name]);
                $branch->organization_id = $organization->id;
            }

            if (! static::where('organization_id', $branch->organization_id)->exists()) {
                $branch->is_main = true;
            }
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function medications()
    {
        return $this->hasMany(Medication::class);
    }

    public function staff()
    {
        return $this->hasMany(User::class, 'assigned_pharmacy_id')->where('cura_role', 'sales_person');
    }

    /**
     * Get the users who have saved this pharmacy.
     */
    public function followers()
    {
        return $this->belongsToMany(User::class, 'saved_pharmacies');
    }

    /** Branches that take online orders. A switched-off branch is left out of the marketplace. */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Every branch of the organization this owner runs, main branch first. */
    public static function branchesOf(User $owner)
    {
        return static::where('user_id', $owner->id)->orderByDesc('is_main')->orderBy('id');
    }

    /**
     * The branch this person is working in. Staff belong to one branch.
     * The owner works in whichever branch they picked with the branch
     * switcher, starting with the main one.
     */
    public static function resolveForUser(User $user): ?self
    {
        if ($user->role === 'sales_person') {
            if ($user->assigned_pharmacy_id) {
                return self::find($user->assigned_pharmacy_id);
            }

            return self::where('user_id', $user->created_by_user_id)->orderByDesc('is_main')->orderBy('id')->first();
        }

        $branches = self::branchesOf($user)->get();
        $chosen = Auth::id() === $user->id ? (int) session('branch_id') : 0;

        return $branches->firstWhere('id', $chosen) ?? $branches->first();
    }

    /** The branch of whoever is signed in. */
    public static function current(): ?self
    {
        return Auth::check() ? static::resolveForUser(Auth::user()) : null;
    }

    /** May this person see and work in this branch? The owner: any of theirs. Staff: only their own. */
    public function isOpenTo(User $user): bool
    {
        if ($user->role === 'vendor') {
            return (int) $this->user_id === (int) $user->id;
        }

        return $user->role === 'sales_person' && (int) static::resolveForUser($user)?->id === (int) $this->id;
    }

    /** How customers see it: "Organization · Branch" once a business has more than one branch. */
    public function displayName(): string
    {
        $organization = $this->organization;

        if (! $organization || $organization->name === $this->name || static::where('organization_id', $organization->id)->count() < 2) {
            return $this->name;
        }

        return $organization->name . ' · ' . $this->name;
    }

    public function fullAddress(): string
    {
        return collect([$this->address, $this->city, $this->state])->filter()->unique()->implode(', ');
    }
}
