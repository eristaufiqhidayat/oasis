<?php

namespace App\Http\Controllers\Master;

use App\Enums\OutletStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Outlet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Outlet baru (NOO) yang didaftarkan motoris dari HP: disetujui jadi aktif atau ditolak. */
class OutletVerificationController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status', OutletStatus::Pending->value);

        $outlets = Outlet::visibleTo($request->user())->with(['depot', 'territory', 'registeredBy'])
            ->where('status', $status)
            ->whereNotNull('registered_by')
            ->when($this->selectedDepot($request), fn ($q, $id) => $q->where('depot_id', $id))
            ->latest()->paginate(25)->withQueryString()
            ->through(fn (Outlet $o) => [
                'id' => $o->id, 'code' => $o->code, 'name' => $o->name, 'owner' => $o->owner_name, 'phone' => $o->phone,
                'address' => $o->address, 'depot' => $o->depot?->name, 'territory' => $o->territory?->name,
                'latitude' => $o->latitude, 'longitude' => $o->longitude, 'registered_by' => $o->registeredBy?->name,
                'registered_at' => $o->created_at?->toDateTimeString(), 'note' => $o->verification_note,
                'status' => ['label' => $o->status->label(), 'variant' => OutletController::STATUS_VARIANT[$o->status->value]],
            ]);

        return Inertia::render('Master/OutletVerification', [
            'outlets' => $outlets,
            'filters' => ['status' => $status, 'depot' => $this->selectedDepot($request)],
            'depots' => $this->depotOptions($request),
            'statuses' => self::options([OutletStatus::Pending->value => 'Menunggu', OutletStatus::Active->value => 'Disetujui', OutletStatus::Rejected->value => 'Ditolak']),
        ]);
    }

    public function verify(Request $request, int $id): RedirectResponse
    {
        $outlet = Outlet::visibleTo($request->user())->findOrFail($id);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'required_if:decision,reject', 'string', 'max:255'],
        ], ['note.required_if' => 'Alasan penolakan wajib diisi.']);

        $approve = $data['decision'] === 'approve';
        $outlet->update([
            'status' => $approve ? OutletStatus::Active : OutletStatus::Rejected,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
            'verification_note' => $data['note'] ?? null,
        ]);

        ActivityLog::record('outlet.verify', "Outlet {$outlet->name} ".($approve ? 'disetujui' : 'ditolak'), $outlet);

        return back()->with('sukses', "Outlet {$outlet->name} ".($approve ? 'disetujui dan aktif.' : 'ditolak.'));
    }
}
