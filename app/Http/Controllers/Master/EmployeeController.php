<?php

namespace App\Http\Controllers\Master;

use App\Enums\Position;
use App\Http\Controllers\ResourceController;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeController extends ResourceController
{
    protected string $model = Employee::class;

    protected string $slug = 'master/karyawan';

    protected string $title = 'Karyawan';

    protected ?string $subtitle = 'Motoris, koordinator, dan staf depo. Motoris yang punya akun pengguna bisa login di aplikasi HP.';

    protected string $viewPermission = 'employee.view';

    protected string $managePermission = 'employee.manage';

    protected array $search = ['name', 'code', 'phone'];

    protected function query(Request $request): Builder
    {
        return Employee::visibleTo($request->user())->with(['depot', 'supervisor', 'user']);
    }

    protected function filters(Request $request): array
    {
        return [
            ['name' => 'depot', 'label' => 'Depo', 'options' => $this->depotOptions($request)],
            ['name' => 'jabatan', 'label' => 'Jabatan', 'options' => self::options(Position::options())],
            ['name' => 'status', 'label' => 'Status', 'options' => self::options(['1' => 'Aktif', '0' => 'Nonaktif'])],
        ];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->integer('depot'), fn ($q, $id) => $q->where('depot_id', $id))
            ->when($request->query('jabatan'), fn ($q, $v) => $q->where('position', $v))
            ->when($request->query('status') !== null && $request->query('status') !== '', fn ($q) => $q->where('is_active', $request->boolean('status')));
    }

    protected function columns(): array
    {
        return [
            'code' => ['label' => 'Kode'], 'name' => ['label' => 'Nama'], 'position' => ['label' => 'Jabatan'], 'depot' => ['label' => 'Depo'],
            'supervisor' => ['label' => 'Atasan'], 'phone' => ['label' => 'No. HP'], 'user' => ['label' => 'Akun'], 'status' => ['label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(Request $request, ?Model $model): array
    {
        $supervisors = Employee::visibleTo($request->user())->active()
            ->whereIn('position', [Position::Coordinator->value, Position::DepotHead->value])
            ->when($model, fn ($q) => $q->whereKeyNot($model->getKey()))
            ->orderBy('name')->pluck('name', 'id');

        return [
            ['name' => 'code', 'label' => 'Kode / NIK', 'rules' => ['required', 'string', 'max:30', self::unique('employees', 'code', $model)]],
            ['name' => 'name', 'label' => 'Nama', 'rules' => ['required', 'string', 'max:255']],
            ['name' => 'position', 'label' => 'Jabatan', 'type' => 'select', 'options' => self::options(Position::options()), 'rules' => ['required', Rule::enum(Position::class)]],
            ['name' => 'depot_id', 'label' => 'Depo', 'type' => 'select', 'options' => $this->depotOptions($request), 'rules' => ['nullable', 'required_if:position,salesman,coordinator,depot_head,warehouse,cashier', Rule::in(array_column($this->depotOptions($request), 'value'))]],
            ['name' => 'supervisor_id', 'label' => 'Atasan (koordinator)', 'type' => 'combobox', 'options' => self::options($supervisors), 'rules' => ['nullable', 'exists:employees,id']],
            ['name' => 'phone', 'label' => 'No. HP', 'rules' => ['nullable', 'string', 'max:30']],
            ['name' => 'joined_on', 'label' => 'Tanggal masuk', 'type' => 'date', 'rules' => ['nullable', 'date']],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'rules' => ['boolean']],
        ];
    }

    protected function values(Model $model): array
    {
        return $model->only(['code', 'name', 'depot_id', 'supervisor_id', 'phone', 'is_active'])
            + ['position' => $model->position->value, 'joined_on' => $model->joined_on?->toDateString()];
    }

    protected function row(Model $model): array
    {
        return [
            'code' => $model->code, 'name' => $model->name, 'position' => $model->position->label(), 'depot' => $model->depot?->name,
            'supervisor' => $model->supervisor?->name, 'phone' => $model->phone, 'user' => $model->user?->username,
            'status' => self::badge($model->is_active),
        ];
    }
}
