<?php

namespace App\Http\Controllers;

use App\Models\Depot;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

abstract class Controller
{
    /** Depo dalam cakupan akun (null = semua). */
    protected function depotIds(Request $request): ?array
    {
        return $request->user()->depotIds();
    }

    /** Pilihan depo untuk filter/form: [{value, label}]. */
    protected function depotOptions(Request $request): array
    {
        return Depot::visibleTo($request->user())->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($d) => ['value' => $d->id, 'label' => $d->name])->all();
    }

    /** Depo dari query ?depot=; akun satu depo otomatis memakai depo itu. */
    protected function selectedDepot(Request $request, bool $defaultToFirst = false): ?int
    {
        $ids = $this->depotIds($request);
        $value = $request->integer('depot') ?: null;

        if ($value && ($ids === null || in_array($value, $ids, true))) {
            return $value;
        }

        if ($ids !== null && count($ids) === 1) {
            return $ids[0];
        }

        return $defaultToFirst ? Depot::visibleTo($request->user())->where('is_active', true)->orderBy('name')->value('id') : null;
    }

    protected function date(Request $request, string $key = 'tanggal'): Carbon
    {
        $value = $request->query($key);

        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? Carbon::parse($value) : Carbon::today();
    }

    /** @return array<int, array{value: int, label: string}> */
    protected function salesmanOptions(Request $request, ?int $depotId = null): array
    {
        return Employee::visibleTo($request->user())->salesmen()->active()
            ->when($depotId, fn ($q) => $q->where('depot_id', $depotId))
            ->orderBy('name')->get(['id', 'name', 'code'])
            ->map(fn ($e) => ['value' => $e->id, 'label' => "{$e->name} ({$e->code})"])->all();
    }

    protected function abortUnlessDepot(Request $request, ?int $depotId): void
    {
        abort_unless($request->user()->canAccessDepot($depotId), 404);
    }

    /** Ubah [id => label] menjadi [{value, label}] (urutan terjaga di JavaScript). */
    protected static function options(iterable $pairs): array
    {
        $out = [];
        foreach ($pairs as $value => $label) {
            $out[] = ['value' => $value, 'label' => $label];
        }

        return $out;
    }
}
