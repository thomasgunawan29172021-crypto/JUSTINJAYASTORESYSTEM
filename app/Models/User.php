<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'extra_roles', 'branch_id', 'is_active', 'base_salary'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => UserRole::class,
            'extra_roles'       => 'array',
            'is_active'         => 'boolean',
        ];
    }

    /** Cabang utama/default. Dipertahankan agar fitur lama tetap kompatibel. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Semua cabang tempat akun ini diizinkan melakukan absensi. */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user')->withTimestamps();
    }

    public function workSchedule(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WorkSchedule::class);
    }

    public function attendances(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function stores(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'store_user');
    }

    public function brands(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Brand::class, 'brand_user');
    }

    /* ==================== MULTI-ROLE ====================
       role       = jabatan UTAMA (label, payroll, identitas) — tidak berubah artinya.
       extra_roles= role tambahan, cuma nambah HAK AKSES.
       SEMUA pengecekan akses harus lewat method di bawah ini ($user->canX()),
       BUKAN $user->role->canX() — versi role-> cuma lihat jabatan utama dan
       bakal diam-diam ngabaikan role tambahan. */

    /**
     * Semua role yang dipegang (utama + tambahan), sebagai enum.
     *
     * @return array<int, UserRole>
     */
    public function allRoles(): array
    {
        $extras = array_values(array_filter(array_map(
            fn (string $v) => UserRole::tryFrom($v),
            $this->extra_roles ?? []
        )));

        return array_unique([$this->role, ...$extras], SORT_REGULAR);
    }

    public function hasRole(UserRole $role): bool
    {
        return in_array($role, $this->allRoles(), true);
    }

    /** true kalau SALAH SATU role yang dipegang lolos pengecekan $check. */
    protected function anyRole(callable $check): bool
    {
        foreach ($this->allRoles() as $r) {
            if ($check($r)) {
                return true;
            }
        }

        return false;
    }

    // CEO sengaja TIDAK lewat anyRole: CEO cuma sah sebagai jabatan utama.
    public function isCeo(): bool                   { return $this->role->isCeo(); }
    public function isManager(): bool               { return $this->anyRole(fn ($r) => $r->isManager()); }
    public function canAccessService(): bool        { return $this->anyRole(fn ($r) => $r->canAccessService()); }
    public function canAccessFinance(): bool        { return $this->anyRole(fn ($r) => $r->canAccessFinance()); }
    public function canCreateWarrantyClaim(): bool  { return $this->anyRole(fn ($r) => $r->canCreateWarrantyClaim()); }
    public function canProcessWarrantyClaim(): bool { return $this->anyRole(fn ($r) => $r->canProcessWarrantyClaim()); }
    public function canManageSosmed(): bool         { return $this->anyRole(fn ($r) => $r->canManageSosmed()); }
}
