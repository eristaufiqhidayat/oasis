<?php

namespace App\Models;

use App\Enums\Position;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = ['name', 'username', 'email', 'password', 'role_id', 'employee_id', 'all_depots', 'is_active', 'must_change_password'];

    protected $attributes = ['is_active' => true, 'all_depots' => false, 'must_change_password' => false];

    protected $hidden = ['password', 'remember_token'];

    /** Cache daftar depo per request (null = semua depo). */
    private ?array $depotIdCache = null;

    private bool $depotIdCached = false;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'all_depots' => 'boolean',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function depots(): BelongsToMany
    {
        return $this->belongsToMany(Depot::class);
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->is_active && (bool) $this->role?->allows($permission);
    }

    /**
     * Depo dalam cakupan akun: null = semua depo. Akun yang terhubung ke karyawan tanpa pivot
     * otomatis dibatasi ke depo karyawannya.
     *
     * @return array<int>|null
     */
    public function depotIds(): ?array
    {
        if ($this->depotIdCached) {
            return $this->depotIdCache;
        }

        $this->depotIdCached = true;

        if ($this->all_depots || $this->role?->allows('*')) {
            return $this->depotIdCache = null;
        }

        $ids = $this->depots()->pluck('depots.id')->map(fn ($id) => (int) $id)->all();

        if ($ids === [] && $this->employee?->depot_id) {
            $ids = [(int) $this->employee->depot_id];
        }

        return $this->depotIdCache = $ids;
    }

    public function canAccessDepot(?int $depotId): bool
    {
        $ids = $this->depotIds();

        return $ids === null || ($depotId !== null && in_array($depotId, $ids, true));
    }

    public function forgetDepotCache(): void
    {
        $this->depotIdCached = false;
        $this->depotIdCache = null;
    }

    public function isSalesman(): bool
    {
        return $this->employee?->position === Position::Salesman;
    }
}
