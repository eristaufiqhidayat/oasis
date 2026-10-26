<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\ResourceController;
use App\Models\ActivityLog;
use App\Models\Outlet;
use App\Models\SalesRoute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SalesRouteController extends ResourceController
{
    protected string $model = SalesRoute::class;

    protected string $slug = 'master/rute';

    protected string $title = 'Rute Kunjungan';

    protected ?string $subtitle = 'Daftar outlet berurutan per motoris per hari. Rute hari ini dikirim ke HP sebagai daftar kunjungan.';

    protected string $viewPermission = 'outlet.view';

    protected string $managePermission = 'outlet.manage';

    protected function query(Request $request): Builder
    {
        return SalesRoute::visibleTo($request->user())->with(['depot', 'employee'])->withCount('outlets');
    }

    protected function filters(Request $request): array
    {
        return [
            ['name' => 'depot', 'label' => 'Depo', 'options' => $this->depotOptions($request)],
            ['name' => 'hari', 'label' => 'Hari', 'options' => self::options(SalesRoute::WEEKDAYS)],
        ];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->integer('depot'), fn ($q, $id) => $q->where('depot_id', $id))
            ->when($request->integer('hari'), fn ($q, $d) => $q->where('weekday', $d));
    }

    protected function columns(): array
    {
        return [
            'name' => ['label' => 'Nama rute'], 'depot' => ['label' => 'Depo'], 'employee' => ['label' => 'Motoris'],
            'weekday' => ['label' => 'Hari'], 'outlets' => ['label' => 'Outlet', 'type' => 'number'], 'status' => ['label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(Request $request, ?Model $model): array
    {
        return [
            ['name' => 'name', 'label' => 'Nama rute', 'rules' => ['required', 'string', 'max:255']],
            ['name' => 'depot_id', 'label' => 'Depo', 'type' => 'select', 'options' => $this->depotOptions($request), 'rules' => ['required', Rule::in(array_column($this->depotOptions($request), 'value'))]],
            ['name' => 'employee_id', 'label' => 'Motoris', 'type' => 'combobox', 'options' => $this->salesmanOptions($request),
                'rules' => ['nullable', Rule::exists('employees', 'id')->where('depot_id', $request->input('depot_id'))]],
            ['name' => 'weekday', 'label' => 'Hari', 'type' => 'select', 'options' => self::options(SalesRoute::WEEKDAYS), 'rules' => ['nullable', 'integer', 'between:1,7']],
            ['name' => 'is_active', 'label' => 'Aktif', 'type' => 'checkbox', 'rules' => ['boolean']],
        ];
    }

    protected function rowActions(Model $model): array
    {
        return [['key' => 'outlets', 'label' => 'Atur outlet', 'href' => "/master/rute/{$model->id}"]];
    }

    protected function row(Model $model): array
    {
        return [
            'name' => $model->name, 'depot' => $model->depot?->name, 'employee' => $model->employee?->name,
            'weekday' => SalesRoute::WEEKDAYS[$model->weekday] ?? 'Setiap hari', 'outlets' => $model->outlets_count,
            'status' => self::badge($model->is_active),
        ];
    }

    public function show(Request $request, int $id): Response
    {
        abort_unless($request->user()->can('outlet.view'), 403);
        $route = SalesRoute::visibleTo($request->user())->with(['depot', 'employee', 'outlets.territory'])->findOrFail($id);

        return Inertia::render('Master/RouteOutlets', [
            'route' => [
                'id' => $route->id, 'name' => $route->name, 'depot' => $route->depot?->name, 'employee' => $route->employee?->name,
                'weekday' => SalesRoute::WEEKDAYS[$route->weekday] ?? 'Setiap hari',
            ],
            'outlets' => $route->outlets->map(fn (Outlet $o) => [
                'id' => $o->id, 'code' => $o->code, 'name' => $o->name, 'address' => $o->address, 'territory' => $o->territory?->name,
            ])->values(),
            'available' => Outlet::where('depot_id', $route->depot_id)->where('status', 'active')->orderBy('name')->limit(5000)
                ->get(['id', 'code', 'name'])->map(fn ($o) => ['value' => $o->id, 'label' => "{$o->name} ({$o->code})"])->all(),
            'canManage' => $request->user()->can('outlet.manage'),
        ]);
    }

    public function syncOutlets(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->can('outlet.manage'), 403);
        $route = SalesRoute::visibleTo($request->user())->findOrFail($id);

        $data = $request->validate([
            'outlets' => ['present', 'array'],
            'outlets.*' => ['integer', Rule::exists('outlets', 'id')->where('depot_id', $route->depot_id)],
        ]);

        $route->outlets()->sync(collect($data['outlets'])->unique()->values()->mapWithKeys(fn ($id, $i) => [$id => ['sequence' => $i + 1]])->all());
        ActivityLog::record('route.outlets', "Outlet rute {$route->name} diperbarui", null, ['route' => $route->id]);

        return back()->with('sukses', 'Urutan outlet rute disimpan.');
    }
}
