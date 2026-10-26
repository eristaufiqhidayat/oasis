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
            'rows' => $employees->map(function (Employee $e) use ($records) {
                // Motoris yang belum absen tidak punya baris di $records.
                $a = $records->get($e->id);

                return [
                    'id' => $e->id, 'name' => $e->name, 'code' => $e->code, 'depot' => $e->depot?->name,
                    'check_in' => $a?->check_in_at?->format('H:i'),
                    'check_out' => $a?->check_out_at?->format('H:i'),
                    'has_location' => $a?->check_in_latitude !== null,
                ];
            })->values(),
            'summary' => ['present' => $records->count(), 'total' => $employees->count()],
        ]);
    }
}
