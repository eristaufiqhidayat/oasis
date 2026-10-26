<?php

namespace App\Http\Controllers\Api;

use App\Enums\TripStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Trip;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    protected function employee(Request $request): Employee
    {
        return $request->user()->employee;
    }

    /** Muatan aktif motoris (sudah dimuat, belum dibongkar). */
    protected function activeTrip(Request $request, bool $required = true): ?Trip
    {
        $trip = Trip::where('employee_id', $this->employee($request)->id)
            ->whereIn('status', [TripStatus::Loaded->value, TripStatus::OnRoute->value])
            ->latest('date')->latest('id')->first();

        if (! $trip && $required) {
            throw new BusinessRuleException('Belum ada muatan aktif. Minta gudang memuat barang terlebih dulu.', 'no_active_trip');
        }

        return $trip;
    }

    protected function ok(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    /** 201 untuk data baru, 200 bila client_uuid yang sama sudah pernah diterima (kirim ulang). */
    protected function created(mixed $model, mixed $data): JsonResponse
    {
        return $this->ok($data, $model->wasRecentlyCreated ? 201 : 200);
    }
}
