<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\ResourceController;
use App\Models\Region;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RegionController extends ResourceController
{
    protected string $model = Region::class;

    protected string $slug = 'master/wilayah';

    protected string $title = 'Wilayah';

    protected ?string $subtitle = 'Pengelompokan depo untuk laporan regional.';

    protected string $viewPermission = 'master.view';

    protected string $managePermission = 'master.manage';

    protected array $search = ['name', 'code'];

    protected function query(Request $request): Builder
    {
        return Region::withCount('depots');
    }

    protected function columns(): array
    {
        return ['code' => ['label' => 'Kode'], 'name' => ['label' => 'Nama'], 'depots' => ['label' => 'Jumlah depo', 'type' => 'number']];
    }

    protected function fields(Request $request, ?Model $model): array
    {
        return [
            ['name' => 'code', 'label' => 'Kode', 'rules' => ['required', 'string', 'max:30', self::unique('regions', 'code', $model)]],
            ['name' => 'name', 'label' => 'Nama', 'rules' => ['required', 'string', 'max:255']],
        ];
    }

    protected function row(Model $model): array
    {
        return ['code' => $model->code, 'name' => $model->name, 'depots' => $model->depots_count];
    }
}
