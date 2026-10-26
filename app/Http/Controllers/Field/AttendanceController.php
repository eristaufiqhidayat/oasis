<?php

namespace App\Http\Controllers\Field;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $date = $this->date($request);
        $depot = $this->selectedDepot($request);

        $employees = Employee::visibleTo($request->user())->active()->salesmen()->with('depot')
            ->when($depot, fn ($q) => $q->where('depot_id', $depot))->orderBy('name')->get();
        $records = Attendance::whereIn('employee_id', $employees->pluck('id'))->whereDate('date', $date)->get()->keyBy('employee_id');

        return Inertia::render('Field/Attendance', [
            'filters' => ['tanggal' => $date->toDateString(), 'depot' => $depot],
            'depots' => $this->depotOptions($request),
            'rows' => $employees->map(fn (Employee $e) => [
                'id' => $e->id, 'name' => $e->name, 'code' => $e->code, 'depot' => $e->depot?->name,
                'check_in' => $records[$e->id]?->check_in_at?->format('H:i'),
                'check_out' => $records[$e->id]?->check_out_at?->format('H:i'),
                'has_location' => $records[$e->id]?->check_in_latitude !== null,
            ])->values(),
            'summary' => ['present' => $records->count(), 'total' => $employees->count()],
        ]);
    }
}
