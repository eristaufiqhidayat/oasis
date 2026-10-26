<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\ResourceController;
use App\Models\Depot;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends ResourceController
{
    protected string $model = User::class;

    protected string $slug = 'pengaturan/pengguna';

    protected string $title = 'Pengguna';

    protected ?string $subtitle = 'Akun web dan aplikasi HP. Akun motoris dihubungkan ke data karyawan supaya bisa login di HP.';

    protected string $viewPermission = 'user.manage';

    protected string $managePermission = 'user.manage';

    protected array $search = ['name', 'username', 'email'];

    protected function query(Request $request): Builder
    {
        $ids = $request->user()->depotIds();

        // Akun dengan cakupan depo terbatas hanya melihat akun lain di depo yang sama.
        return User::with(['role', 'employee', 'depots'])
            ->when($ids !== null, fn ($q) => $q->where('all_depots', false)->where(fn ($w) => $w
                ->whereHas('depots', fn ($d) => $d->whereIn('depots.id', $ids))
                ->orWhereHas('employee', fn ($e) => $e->whereIn('depot_id', $ids))));
    }

    protected function filters(Request $request): array
    {
        return [['name' => 'peran', 'label' => 'Peran', 'options' => self::options(Role::orderBy('name')->pluck('name', 'id'))]];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->integer('peran'), fn ($q, $id) => $q->where('role_id', $id));
    }

    protected function columns(): array
    {
        return [
            'username' => ['label' => 'Username'], 'name' => ['label' => 'Nama'], 'role' => ['label' => 'Peran'],
            'employee' => ['label' => 'Karyawan'], 'scope' => ['label' => 'Cakupan depo'], 'last_login' => ['label' => 'Login terakhir'],
            'status' => ['label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(Request $request, ?Model $model): array
    {
        $roles = Role::orderBy('name')->when($request->user()->depotIds() !== null, fn ($q) => $q->where('permissions', 'not like', '%"*"%'))->pluck('name', 'id');
        $employees = Employee::visibleTo($request->user())->active()->with('depot')->orderBy('name')->get()
            ->map(fn ($e) => ['value' => $e->id, 'label' => "{$e->name} ({$e->code}) — {$e->position->label()}"])->all();

        return [
            ['name' => 'username', 'label' => 'Username', 'rules' => ['required', 'string', 'max:60', 'alpha_dash', self::unique('users', 'username', $model)]],
            ['name' => 'name', 'label' => 'Nama', 'rules' => ['required', 'string', 'max:255']],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'rules' => ['nullable', 'email', 'max:255', self::unique('users', 'email', $model)]],
            ['name' => 'role_id', 'label' => 'Peran', 'type' => 'select', 'options' => self::options($roles), 'rules' => ['required', Rule::in($roles->keys()->all())]],
            ['name' => 'employee_id', 'label' => 'Karyawan', 'type' => 'combobox', 'options' => $employees, 'help' => 'Wajib untuk akun motoris (login HP).',
                'rules' => ['nullable', Rule::in(array_column($employees, 'value')), self::unique('users', 'employee_id', $model)]],
            ['name' => 'all_depots', 'label' => 'Boleh melihat semua depo', 'type' => 'checkbox', 'rules' => ['boolean']],
            ['name' => 'depot_ids', 'label' => 'Depo', 'type' => 'multiselect', 'options' => $this->depotOptions($request), 'help' => 'Kosong = depo karyawan yang dipilih.',
                'rules' => ['array'], 'show_if' => ['all_depots' => false]],
            ['name' => 'password', 'label' => $model ? 'Password baru (kosongkan bila tetap)' : 'Password', 'type' => 'password',
                'rules' => [$model ? 'nullable' : 'required', Password::min(8)]],
            ['name' => 'must_change_password', 'label' => 'Wajib ganti password saat masuk', 'type' => 'checkbox', 'rules' => ['boolean']],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'rules' => ['boolean']],
        ];
    }

    protected function values(Model $model): array
    {
        return $model->only(['username', 'name', 'email', 'role_id', 'employee_id', 'all_depots', 'must_change_password', 'is_active'])
            + ['depot_ids' => $model->depots->pluck('id')->all(), 'password' => ''];
    }

    protected function save(Request $request, Model $model, array $data): void
    {
        $allowed = array_column($this->depotOptions($request), 'value');
        $depots = array_values(array_intersect(array_map('intval', $data['depot_ids'] ?? []), $allowed));

        // Hanya akun pusat yang boleh memberi akses semua depo.
        if ($request->user()->depotIds() !== null) {
            $data['all_depots'] = false;
        }

        $attributes = Arr::except($data, ['depot_ids', 'password']);
        if (! empty($data['password'])) {
            $attributes['password'] = $data['password'];
        }

        $model->fill($attributes)->save();
        $model->depots()->sync(($data['all_depots'] ?? false) ? [] : $depots);
    }

    protected function canDelete(Model $model): bool
    {
        return $model->getKey() !== auth()->id();
    }

    protected function label(Model $model): string
    {
        return $model->username;
    }

    protected function row(Model $model): array
    {
        return [
            'username' => $model->username, 'name' => $model->name, 'role' => $model->role?->name, 'employee' => $model->employee?->name,
            'scope' => $model->all_depots ? 'Semua depo' : ($model->depots->pluck('name')->join(', ') ?: ($model->employee?->depot_id ? Depot::find($model->employee->depot_id)?->name : '-')),
            'last_login' => $model->last_login_at?->format('d/m/Y H:i'), 'status' => self::badge($model->is_active),
        ];
    }
}
