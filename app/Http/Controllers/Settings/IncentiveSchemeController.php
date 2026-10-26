<?php

namespace App\Http\Controllers\Settings;

use App\Enums\IncentiveTarget;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\IncentiveScheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class IncentiveSchemeController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Settings/IncentiveSchemes', [
            'schemes' => IncentiveScheme::orderBy('applies_to')->orderBy('name')->get()->map(fn (IncentiveScheme $s) => [
                'id' => $s->id, 'name' => $s->name, 'applies_to' => $s->applies_to->value, 'applies_to_label' => $s->applies_to->label(),
                'is_active' => $s->is_active,
                'rules' => [
                    'per_effective_call' => (int) ($s->rules['per_effective_call'] ?? 0),
                    'revenue_percent' => (float) ($s->rules['revenue_percent'] ?? 0),
                    'tiers' => array_values($s->rules['tiers'] ?? []),
                ],
            ]),
            'targets' => self::options(IncentiveTarget::options()),
            'canManage' => $request->user()->can('incentive.manage'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $scheme = IncentiveScheme::create($this->validated($request));
        ActivityLog::record('scheme.create', "Skema insentif {$scheme->name} dibuat", $scheme);

        return back()->with('sukses', "Skema {$scheme->name} dibuat.");
    }

    public function update(Request $request, IncentiveScheme $scheme): RedirectResponse
    {
        $scheme->update($this->validated($request));
        ActivityLog::record('scheme.update', "Skema insentif {$scheme->name} diubah", $scheme);

        return back()->with('sukses', "Skema {$scheme->name} disimpan. Insentif yang sudah disetujui tidak berubah.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', Rule::enum(IncentiveTarget::class)],
            'is_active' => ['boolean'],
            'rules.per_effective_call' => ['nullable', 'integer', 'min:0'],
            'rules.revenue_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'rules.tiers' => ['array'],
            'rules.tiers.*.min_achievement' => ['required', 'numeric', 'min:0', 'max:1000'],
            'rules.tiers.*.amount' => ['required', 'integer', 'min:0'],
        ], [], ['rules.tiers.*.min_achievement' => 'pencapaian minimal', 'rules.tiers.*.amount' => 'nominal bonus']);

        $tiers = collect($data['rules']['tiers'] ?? [])->map(fn ($t) => ['min_achievement' => (float) $t['min_achievement'], 'amount' => (int) $t['amount']])
            ->sortBy('min_achievement')->values()->all();

        return [
            'name' => $data['name'],
            'applies_to' => $data['applies_to'],
            'is_active' => $data['is_active'] ?? true,
            'rules' => [
                'per_effective_call' => (int) ($data['rules']['per_effective_call'] ?? 0),
                'revenue_percent' => (float) ($data['rules']['revenue_percent'] ?? 0),
                'tiers' => $tiers,
            ],
        ];
    }
}
