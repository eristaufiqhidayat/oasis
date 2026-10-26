<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\ResourceController;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CategoryController extends ResourceController
{
    protected string $model = ProductCategory::class;

    protected string $slug = 'master/kategori';

    protected string $title = 'Kategori Produk';

    protected string $viewPermission = 'product.view';

    protected string $managePermission = 'product.manage';

    protected function query(Request $request): Builder
    {
        return ProductCategory::withCount('products');
    }

    protected function columns(): array
    {
        return ['name' => ['label' => 'Nama'], 'products' => ['label' => 'Produk', 'type' => 'number']];
    }

    protected function fields(Request $request, ?Model $model): array
    {
        return [['name' => 'name', 'label' => 'Nama', 'rules' => ['required', 'string', 'max:255', self::unique('product_categories', 'name', $model)]]];
    }

    protected function row(Model $model): array
    {
        return ['name' => $model->name, 'products' => $model->products_count];
    }
}
