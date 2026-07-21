<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'is_system',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'key');
    }

    public function isSuperAdmin(): bool
    {
        return $this->key === 'super_admin';
    }

    /**
     * @param  list<string>  $permissionKeys
     */
    public function syncPermissionKeys(array $permissionKeys): void
    {
        $ids = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id')
            ->all();

        $this->permissions()->sync($ids);
    }

    /**
     * @return list<string>
     */
    public function permissionKeys(): array
    {
        return $this->permissions->pluck('key')->map(fn ($k) => (string) $k)->values()->all();
    }
}
