<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
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

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }

    public function isCentralOwner(): bool
    {
        return $this->hasRole('full-owner');
    }

    public function isBranchOwner(): bool
    {
        return $this->hasRole('owner-mitra');
    }

    public function isStaff(): bool
    {
        return $this->hasRole('karyawan-mitra');
    }

    /** Label role untuk tampilan UI. */
    public function roleLabel(): string
    {
        return match (true) {
            $this->isCentralOwner() => 'Full Owner',
            $this->isBranchOwner() => 'Owner Mitra',
            $this->isStaff() => 'Karyawan',
            default => 'Akses Terbatas',
        };
    }

    /**
     * ID cabang aktif untuk pengguna cabang:
     * ambil dari session bila valid, selain itu cabang pertama.
     */
    public function activeBranchId(): ?int
    {
        if ($this->isCentralOwner()) {
            return null;
        }
        $ids = $this->branches()->where('is_active', true)->orderBy('name')->pluck('branches.id');
        $session = (int) session('active_branch_id', 0);
        if ($session > 0 && $ids->contains($session)) {
            return $session;
        }
        return $ids->first();
    }
}
