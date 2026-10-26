<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\ResourceController;
use App\Models\ActivityLog;
use App\Models\Outlet;
use App\Models\Territory;
use App\Support\Geo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TerritoryController extends ResourceController
{
    protected string $model = Territory::class;

    protected string $slug = 'master/area';

    protected string $title = 'Area Penjualan';

    protected ?string $subtitle = 'Area di bawah depo. Batas area (polygon) digambar di peta dan dipakai untuk mengisi area outlet baru otomatis.';

    protected string $viewPermission = 'master.view';

    protected string $managePermission = 'master.manage';

    protected array $search = ['territories.name', 'territories.code'];

    protected function query(Request $request): Builder
    {
        return Territory::visibleTo($request->user())->with('depot')->withCount('outlets');
    }

    protected function filters(Request $request): array
    {
        return [['name' => 'depot', 'label' => 'Depo', 'options' => $this->depotOptions($request)]];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->integer('depot'), fn ($q, $id) => $q->where('depot_id', $id));
    }

    protected function actions(Request $request): array
    {
        return [['key' => 'map', 'label' => 'Peta area', 'href' => '/master/area/peta', 'variant' => 'outline-primary']];
    }

    protected function columns(): array
    {
        return [
            'code' => ['label' => 'Kode'], 'name' => ['label' => 'Nama'], 'depot' => ['label' => 'Depo'],
            'outlets' => ['label' => 'Outlet', 'type' => 'number'], 'boundary' => ['label' => 'Batas peta', 'type' => 'badge'],
            'status' => ['label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(Request $request, ?Model $model): array
    {
        return [
            ['name' => 'depot_id', 'label' => 'Depo', 'type' => 'select', 'options' => $this->depotOptions($request), 'rules' => ['required', Rule::in($this->allowedDepots($request))]],
            ['name' => 'code', 'label' => 'Kode', 'rules' => ['nullable', 'string', 'max:30']],
            ['name' => 'name', 'label' => 'Nama', 'rules' => ['required', 'string', 'max:255']],
            ['name' => 'color', 'label' => 'Warna di peta', 'type' => 'color', 'rules' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'rules' => ['boolean']],
        ];
    }

    protected function values(Model $model): array
    {
        return $model->only(['depot_id', 'code', 'name', 'color', 'is_active']);
    }

    protected function row(Model $model): array
    {
        return [
            'code' => $model->code, 'name' => $model->name, 'depot' => $model->depot?->name, 'outlets' => $model->outlets_count,
            'boundary' => self::badge($model->boundary !== null, 'Ada', 'Belum'), 'status' => self::badge($model->is_active),
        ];
    }

    private function allowedDepots(Request $request): array
    {
        return array_column($this->depotOptions($request), 'value');
    }

    /** Peta semua area satu depo + penyunting polygon. */
    public function map(Request $request): Response
    {
        abort_unless($request->user()->can('master.view'), 403);
        $depot = $this->selectedDepot($request, true);

        return Inertia::render('Master/TerritoryMap', [
            'depot' => $depot,
            'depots' => $this->depotOptions($request),
            'territories' => Territory::where('depot_id', $depot)->orderBy('name')->get(['id', 'code', 'name', 'color', 'boundary', 'is_active']),
            'outlets' => Outlet::where('depot_id', $depot)->whereNotNull('latitude')->limit(3000)
                ->get(['id', 'name', 'latitude', 'longitude', 'territory_id']),
            'canManage' => $request->user()->can('master.manage'),
        ]);
    }

    public function saveBoundary(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->can('master.manage'), 403);
        $territory = Territory::visibleTo($request->user())->findOrFail($id);

        $data = $request->validate([
            'boundary' => ['nullable', 'array'],
            'boundary.type' => ['required_with:boundary', Rule::in(['Polygon', 'MultiPolygon'])],
            'boundary.coordinates' => ['required_with:boundary', 'array', 'min:1'],
            'assign_outlets' => ['boolean'],
        ]);

        $territory->update(['boundary' => $data['boundary'] ?? null]);
        $assigned = 0;

        if (($data['assign_outlets'] ?? false) && $territory->boundary) {
            Outlet::where('depot_id', $territory->depot_id)->whereNotNull('latitude')->get()
                ->filter(fn ($o) => Geo::contains($territory->boundary, $o->latitude, $o->longitude))
                ->each(function ($o) use ($territory, &$assigned) {
                    $o->update(['territory_id' => $territory->id]);
                    $assigned++;
                });
        }

        ActivityLog::record('territory.boundary', "Batas area {$territory->name} disimpan", $territory, ['outlets' => $assigned]);

        return back()->with('sukses', "Batas area {$territory->name} disimpan".($assigned ? "; {$assigned} outlet dipindah ke area ini." : '.'));
    }
}
