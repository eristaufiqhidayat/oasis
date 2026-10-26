<?php

namespace App\Http\Controllers\Master;

use App\Enums\OutletStatus;
use App\Http\Controllers\ResourceController;
use App\Models\Outlet;
use App\Models\OutletChannel;
use App\Models\Territory;
use App\Services\FieldService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OutletController extends ResourceController
{
    protected string $model = Outlet::class;

    protected string $slug = 'master/outlet';

    protected string $title = 'Outlet';

    protected ?string $subtitle = 'Toko pelanggan. Outlet baru dari HP masuk dengan status menunggu verifikasi.';

    protected string $viewPermission = 'outlet.view';

    protected string $managePermission = 'outlet.manage';

    protected array $search = ['name', 'code', 'owner_name', 'phone', 'address'];

    public const STATUS_VARIANT = ['pending' => 'warning', 'active' => 'success', 'rejected' => 'danger', 'inactive' => 'secondary'];

    protected function query(Request $request): Builder
    {
        return Outlet::visibleTo($request->user())->with(['depot', 'territory', 'channel']);
    }

    protected function filters(Request $request): array
    {
        $depot = $this->selectedDepot($request);

        return [
            ['name' => 'depot', 'label' => 'Depo', 'options' => $this->depotOptions($request)],
            ['name' => 'area', 'label' => 'Area', 'options' => self::options(Territory::visibleTo($request->user())->when($depot, fn ($q) => $q->where('depot_id', $depot))->orderBy('name')->pluck('name', 'id'))],
            ['name' => 'status', 'label' => 'Status', 'options' => self::options(OutletStatus::options())],
        ];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->integer('depot'), fn ($q, $id) => $q->where('depot_id', $id))
            ->when($request->integer('area'), fn ($q, $id) => $q->where('territory_id', $id))
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v));
    }

    protected function columns(): array
    {
        return [
            'code' => ['label' => 'Kode'], 'name' => ['label' => 'Nama'], 'owner' => ['label' => 'Pemilik'], 'phone' => ['label' => 'Telepon'],
            'depot' => ['label' => 'Depo'], 'territory' => ['label' => 'Area'], 'channel' => ['label' => 'Saluran'], 'status' => ['label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(Request $request, ?Model $model): array
    {
        $depots = array_column($this->depotOptions($request), 'value');

        return [
            ['name' => 'code', 'label' => 'Kode', 'rules' => ['required', 'string', 'max:40', self::unique('outlets', 'code', $model)]],
            ['name' => 'name', 'label' => 'Nama outlet', 'rules' => ['required', 'string', 'max:255']],
            ['name' => 'owner_name', 'label' => 'Nama pemilik', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'phone', 'label' => 'Telepon', 'rules' => ['nullable', 'string', 'max:30']],
            ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'district', 'label' => 'Kecamatan', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'city', 'label' => 'Kota/Kab.', 'rules' => ['nullable', 'string', 'max:255']],
            ['name' => 'latitude', 'label' => 'Latitude', 'type' => 'number', 'step' => 'any', 'rules' => ['nullable', 'numeric', 'between:-90,90']],
            ['name' => 'longitude', 'label' => 'Longitude', 'type' => 'number', 'step' => 'any', 'rules' => ['nullable', 'numeric', 'between:-180,180']],
            ['name' => 'depot_id', 'label' => 'Depo', 'type' => 'select', 'options' => $this->depotOptions($request), 'rules' => ['required', Rule::in($depots)]],
            ['name' => 'territory_id', 'label' => 'Area', 'type' => 'combobox', 'help' => 'Kosongkan untuk diisi otomatis dari titik koordinat.',
                'options' => Territory::visibleTo($request->user())->with('depot')->orderBy('name')->get()->map(fn ($t) => ['value' => $t->id, 'label' => "{$t->name} — {$t->depot?->name}"])->all(),
                'rules' => ['nullable', Rule::exists('territories', 'id')->where('depot_id', $request->input('depot_id'))]],
            ['name' => 'channel_id', 'label' => 'Saluran', 'type' => 'select', 'options' => self::options(OutletChannel::orderBy('name')->pluck('name', 'id')), 'rules' => ['nullable', 'exists:outlet_channels,id']],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => self::options(OutletStatus::options()), 'rules' => ['required', Rule::enum(OutletStatus::class)]],
        ];
    }

    protected function values(Model $model): array
    {
        return $model->only(['code', 'name', 'owner_name', 'phone', 'address', 'district', 'city', 'latitude', 'longitude', 'depot_id', 'territory_id', 'channel_id'])
            + ['status' => $model->status->value];
    }

    protected function save(Request $request, Model $model, array $data): void
    {
        if (empty($data['territory_id']) && isset($data['latitude'], $data['longitude'])) {
            $data['territory_id'] = app(FieldService::class)->territoryAt((int) $data['depot_id'], (float) $data['latitude'], (float) $data['longitude'])?->id;
        }

        $model->fill($data)->save();
    }

    protected function row(Model $model): array
    {
        return [
            'code' => $model->code, 'name' => $model->name, 'owner' => $model->owner_name, 'phone' => $model->phone,
            'depot' => $model->depot?->name, 'territory' => $model->territory?->name, 'channel' => $model->channel?->name,
            'status' => ['label' => $model->status->label(), 'variant' => self::STATUS_VARIANT[$model->status->value]],
        ];
    }
}
