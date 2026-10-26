<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\ResourceController;
use App\Models\Depot;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DepotController extends ResourceController
{
    protected string $model = Depot::class;

    protected string $slug = 'master/depo';

    protected string $title = 'Depo';

    protected ?string $subtitle = 'Gudang distribusi tempat motoris memuat barang dan menyetor uang.';

    protected string $viewPermission = 'master.view';

    protected string $managePermission = 'master.manage';

    protected array $search = ['name', 'code', 'city'];

    protected function query(Request $request): Builder
    {
        return Depot::visibleTo($request->user())->with('region')
            ->withCount(['employees as salesmen_count' => fn ($q) => $q->salesmen()->active(), 'outlets']);
    }

    protected function filters(Request $request): array
    {
        return [['name' => 'wilayah', 'label' => 'Wilayah', 'options' => self::options(Region::orderBy('name')->pluck('name', 'id'))]];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->integer('wilayah'), fn ($q, $id) => $q->where('region_id', $id));
    }

    protected function columns(): array
    {
        return [
            'code' => ['label' => 'Kode'], 'name' => ['label' => 'Nama'], 'region' => ['label' => 'Wilayah'], 'city' => ['label' => 'Kota'],
            'salesmen' => ['label' => 'Motoris aktif', 'type' => 'number'], 'outlets' => ['label' => 'Outlet', 'type' => 'number'],
            'status' => ['label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(Request $request, ?Model $model): array
    {
        return [
            ['name' => 'code', 'label' => 'Kode', 'rules' => ['required', 'string', 'max:30', self::unique('depots', 'code', $model)]],
            ['name' => 'name', 'label' => 'Nama', 'rules' => ['required', 'string', 'max:255']],
            ['name' => 'region_id', 'label' => 'Wilayah', 'type' => 'select', 'options' => self::options(Region::orderBy('name')->pluck('name', 'id')), 'rules' => ['nullable', 'exists:regions,id']],
            ['name' => 'city', 'label' => 'Kota', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'phone', 'label' => 'Telepon', 'rules' => ['nullable', 'string', 'max:30']],
            ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'step' => 'any', 'rules' => ['nullable', 'numeric', 'between:-90,90']],
            ['name' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'step' => 'any', 'rules' => ['nullable', 'numeric', 'between:-180,180']],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'rules' => ['boolean']],
        ];
    }

    protected function row(Model $model): array
    {
        return [
            'code' => $model->code, 'name' => $model->name, 'region' => $model->region?->name, 'city' => $model->city,
            'salesmen' => $model->salesmen_count, 'outlets' => $model->outlets_count, 'status' => self::badge($model->is_active),
        ];
    }
}
