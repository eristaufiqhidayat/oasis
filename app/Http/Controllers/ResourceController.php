<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman master generik: tabel + pencarian + filter + form tambah/ubah dalam modal (Pages/Resource/Index.vue).
 * Turunan cukup mendeskripsikan kolom, field form, dan cara menyimpan.
 */
abstract class ResourceController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    protected string $slug;

    protected string $title;

    protected ?string $subtitle = null;

    protected string $viewPermission;

    protected string $managePermission;

    /** Kolom yang dicari dengan ?q= */
    protected array $search = ['name'];

    protected string $orderBy = 'name';

    /** @return array<string, array{label: string, type?: string}> */
    abstract protected function columns(): array;

    /** @return array<int, array{name: string, label: string, type?: string, options?: array, rules?: array, help?: string}> */
    abstract protected function fields(Request $request, ?Model $model): array;

    /** Nilai sel tabel. Sel badge: ['label' => ..., 'variant' => ...]. */
    abstract protected function row(Model $model): array;

    protected function query(Request $request): Builder
    {
        return $this->model::query();
    }

    /** @return array<int, array{name: string, label: string, options: array}> */
    protected function filters(Request $request): array
    {
        return [];
    }

    protected function applyFilters(Builder $query, Request $request): void {}

    /** Nilai awal form ubah. */
    protected function values(Model $model): array
    {
        return collect($this->fields(request(), $model))->mapWithKeys(fn ($f) => [$f['name'] => $model->getAttribute($f['name'])])->all();
    }

    protected function save(Request $request, Model $model, array $data): void
    {
        $model->fill($data)->save();
    }

    protected function canDelete(Model $model): bool
    {
        return true;
    }

    /** Aksi tambahan per baris: [['key', 'label', 'url' => '{id}/...', 'method' => 'post', 'confirm' => ?string, 'variant']] */
    protected function actions(Request $request): array
    {
        return [];
    }

    protected function rowActions(Model $model): array
    {
        return [];
    }

    protected function label(Model $model): string
    {
        return (string) ($model->getAttribute('name') ?? $model->getKey());
    }

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can($this->viewPermission), 403);

        $query = $this->query($request);
        $q = trim((string) $request->query('q'));

        if ($q !== '') {
            $query->where(function (Builder $w) use ($q) {
                foreach ($this->search as $column) {
                    $w->orWhere($column, 'like', "%{$q}%");
                }
            });
        }

        $this->applyFilters($query, $request);
        $canManage = $request->user()->can($this->managePermission);

        $rows = $query->orderBy($this->orderBy)->paginate(25)->withQueryString()
            ->through(fn (Model $m) => [
                'id' => $m->getKey(),
                'cells' => $this->row($m),
                'values' => $canManage ? $this->values($m) : null,
                'can_delete' => $canManage && $this->canDelete($m),
                'actions' => $this->rowActions($m),
            ]);

        return Inertia::render('Resource/Index', [
            'resource' => [
                'slug' => $this->slug,
                'title' => $this->title,
                'subtitle' => $this->subtitle,
                'columns' => collect($this->columns())->map(fn ($c, $key) => ['key' => $key, 'label' => $c['label'], 'type' => $c['type'] ?? 'text'])->values(),
                'fields' => $canManage ? array_map(fn ($f) => collect($f)->except('rules')->all() + ['type' => 'text'], $this->fields($request, null)) : [],
                'filters' => $this->filters($request),
                'actions' => $this->actions($request),
                'canManage' => $canManage,
            ],
            'rows' => $rows,
            'query' => (object) $request->only(array_merge(['q'], array_column($this->filters($request), 'name'))),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can($this->managePermission), 403);

        $model = new $this->model;
        $this->save($request, $model, $this->validated($request, null));
        ActivityLog::record('create', "{$this->title}: tambah {$this->label($model)}", $model);

        return back()->with('sukses', "{$this->title} \"{$this->label($model)}\" ditambahkan.");
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->can($this->managePermission), 403);

        $model = $this->query($request)->findOrFail($id);
        $this->save($request, $model, $this->validated($request, $model));
        ActivityLog::record('update', "{$this->title}: ubah {$this->label($model)}", $model);

        return back()->with('sukses', "{$this->title} \"{$this->label($model)}\" disimpan.");
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        abort_unless($request->user()->can($this->managePermission), 403);

        $model = $this->query($request)->findOrFail($id);
        $label = $this->label($model);

        if (! $this->canDelete($model)) {
            return back()->with('galat', "\"{$label}\" tidak bisa dihapus.");
        }

        try {
            $model->delete();
        } catch (QueryException) {
            return back()->with('galat', "\"{$label}\" masih dipakai data lain; nonaktifkan saja.");
        }

        ActivityLog::record('delete', "{$this->title}: hapus {$label}", null, ['id' => $id]);

        return back()->with('sukses', "\"{$label}\" dihapus.");
    }

    protected function validated(Request $request, ?Model $model): array
    {
        $rules = collect($this->fields($request, $model))->mapWithKeys(fn ($f) => [$f['name'] => $f['rules'] ?? ['nullable']])->all();
        $labels = collect($this->fields($request, $model))->mapWithKeys(fn ($f) => [$f['name'] => strtolower($f['label'])])->all();

        return $request->validate($rules, [], $labels);
    }

    // ---- Pembantu deklarasi field ----

    protected static function unique(string $table, string $column, ?Model $model): Unique
    {
        return Rule::unique($table, $column)->ignore($model?->getKey());
    }

    protected static function badge(bool $on, string $yes = 'Aktif', string $no = 'Nonaktif'): array
    {
        return ['label' => $on ? $yes : $no, 'variant' => $on ? 'success' : 'secondary'];
    }
}
