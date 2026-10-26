<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\ResourceController;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Pricing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ProductController extends ResourceController
{
    protected string $model = Product::class;

    protected string $slug = 'master/produk';

    protected string $title = 'Produk';

    protected ?string $subtitle = 'Produk yang dijual motoris. Produk galon mencatat saldo galon di outlet. Harga diatur di menu Harga Jual.';

    protected string $viewPermission = 'product.view';

    protected string $managePermission = 'product.manage';

    protected array $search = ['name', 'sku'];

    private ?array $prices = null;

    protected function query(Request $request): Builder
    {
        return Product::with('category');
    }

    protected function filters(Request $request): array
    {
        return [
            ['name' => 'kategori', 'label' => 'Kategori', 'options' => self::options(ProductCategory::orderBy('name')->pluck('name', 'id'))],
            ['name' => 'status', 'label' => 'Status', 'options' => self::options(['1' => 'Aktif', '0' => 'Nonaktif'])],
        ];
    }

    protected function applyFilters(Builder $query, Request $request): void
    {
        $query->when($request->integer('kategori'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->query('status') !== null && $request->query('status') !== '', fn ($q) => $q->where('is_active', $request->boolean('status')));
    }

    protected function columns(): array
    {
        return [
            'sku' => ['label' => 'SKU'], 'name' => ['label' => 'Nama'], 'category' => ['label' => 'Kategori'], 'unit' => ['label' => 'Satuan'],
            'case' => ['label' => 'Isi/dus', 'type' => 'number'], 'price' => ['label' => 'Harga nasional', 'type' => 'money'],
            'container' => ['label' => 'Galon', 'type' => 'badge'], 'status' => ['label' => 'Status', 'type' => 'badge'],
        ];
    }

    protected function fields(Request $request, ?Model $model): array
    {
        return [
            ['name' => 'sku', 'label' => 'SKU', 'rules' => ['required', 'string', 'max:40', self::unique('products', 'sku', $model)]],
            ['name' => 'name', 'label' => 'Nama', 'rules' => ['required', 'string', 'max:255']],
            ['name' => 'category_id', 'label' => 'Kategori', 'type' => 'select', 'options' => self::options(ProductCategory::orderBy('name')->pluck('name', 'id')), 'rules' => ['nullable', 'exists:product_categories,id']],
            ['name' => 'unit', 'label' => 'Satuan', 'rules' => ['required', 'string', 'max:20']],
            ['name' => 'units_per_case', 'label' => 'Isi per dus', 'type' => 'number', 'rules' => ['required', 'integer', 'min:1']],
            ['name' => 'tracks_container', 'label' => 'Galon (wadah wajib kembali)', 'type' => 'checkbox', 'rules' => ['boolean']],
            ['name' => 'is_active', 'label' => 'Aktif (bisa dimuat & dijual)', 'type' => 'checkbox', 'rules' => ['boolean']],
        ];
    }

    protected function row(Model $model): array
    {
        $this->prices ??= app(Pricing::class)->priceList(null);

        return [
            'sku' => $model->sku, 'name' => $model->name, 'category' => $model->category?->name, 'unit' => $model->unit,
            'case' => $model->units_per_case, 'price' => $this->prices[$model->id] ?? null,
            'container' => self::badge($model->tracks_container, 'Galon', '-'), 'status' => self::badge($model->is_active),
        ];
    }
}
