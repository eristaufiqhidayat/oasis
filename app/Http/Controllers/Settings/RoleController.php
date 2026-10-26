<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Roles', [
            'roles' => Role::withCount('users')->orderBy('name')->get()->map(fn (Role $r) => [
                'id' => $r->id, 'slug' => $r->slug, 'name' => $r->name, 'description' => $r->description,
                'permissions' => $r->permissions, 'is_system' => $r->is_system, 'users' => $r->users_count,
            ]),
            'groups' => collect(config('permissions.groups'))->map(fn ($items, $group) => [
                'label' => $group,
                'items' => self::options($items),
            ])->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $role = Role::create($data + ['slug' => $this->slug($data['name'])]);
        ActivityLog::record('role.create', "Peran {$role->name} dibuat", $role);

        return back()->with('sukses', "Peran {$role->name} dibuat.");
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        if (in_array('*', $role->permissions ?? [], true)) {
            return back()->with('galat', 'Izin peran Super Admin tidak bisa diubah.');
        }

        $role->update($this->validated($request, $role));
        ActivityLog::record('role.update', "Peran {$role->name} diubah", $role);

        return back()->with('sukses', "Peran {$role->name} disimpan.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system || $role->users()->exists()) {
            return back()->with('galat', 'Peran bawaan atau yang masih dipakai pengguna tidak bisa dihapus.');
        }

        $role->delete();

        return back()->with('sukses', "Peran {$role->name} dihapus.");
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        $keys = collect(config('permissions.groups'))->flatMap(fn ($items) => array_keys($items))->all();

        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array'],
            'permissions.*' => [Rule::in($keys)],
        ], [], ['name' => 'nama peran']);
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'peran';
        $slug = $base;
        $i = 2;

        while (Role::where('slug', $slug)->exists()) {
            $slug = $base.'_'.$i++;
        }

        return $slug;
    }
}
