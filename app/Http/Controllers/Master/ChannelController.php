<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\ResourceController;
use App\Models\OutletChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ChannelController extends ResourceController
{
    protected string $model = OutletChannel::class;

    protected string $slug = 'master/saluran';

    protected string $title = 'Saluran Outlet';

    protected ?string $subtitle = 'Jenis outlet (warung, toko kelontong, depot isi ulang, horeka, …).';

    protected string $viewPermission = 'master.view';

    protected string $managePermission = 'master.manage';

    protected array $search = ['name', 'code'];

    protected function columns(): array
    {
        return ['code' => ['label' => 'Kode'], 'name' => ['label' => 'Nama']];
    }

    protected function fields(Request $request, ?Model $model): array
    {
        return [
            ['name' => 'code', 'label' => 'Kode', 'rules' => ['required', 'string', 'max:30', self::unique('outlet_channels', 'code', $model)]],
            ['name' => 'name', 'label' => 'Nama', 'rules' => ['required', 'string', 'max:255']],
        ];
    }

    protected function row(Model $model): array
    {
        return ['code' => $model->code, 'name' => $model->name];
    }
}
