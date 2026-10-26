<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q'));

        return Inertia::render('Settings/ActivityLog', [
            'logs' => ActivityLog::with('user')
                ->when($q !== '', fn ($w) => $w->where('description', 'like', "%{$q}%"))
                ->latest('id')->paginate(50)->withQueryString()
                ->through(fn (ActivityLog $l) => [
                    'id' => $l->id, 'at' => $l->created_at?->format('d/m/Y H:i:s'), 'user' => $l->user?->username ?? 'sistem',
                    'action' => $l->action, 'description' => $l->description, 'ip' => $l->ip_address,
                ]),
            'q' => $q,
        ]);
    }
}
