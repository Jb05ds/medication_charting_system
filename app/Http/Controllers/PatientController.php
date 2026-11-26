<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMedicationOrderRequest;
use App\Http\Resources\MedicationOrderResource;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PatientResource::collection(
            Patient::query()->orderBy('name')->get()
        );
    }

    public function orders(Patient $patient): AnonymousResourceCollection
    {
        return MedicationOrderResource::collection(
            $patient->medicationOrders()
                ->with('times')
                ->orderBy('drug_name')
                ->get()
        );
    }

    public function store(StoreMedicationOrderRequest $request, Patient $patient): JsonResponse
    {
        $validated = $request->validated();
        $times = $validated['times'];
        unset($validated['times']);

        $order = DB::transaction(function () use ($patient, $validated, $times) {
            $order = $patient->medicationOrders()->create($validated);
            $order->times()->createMany(
                array_map(fn (string $time): array => ['time' => $time], $times)
            );

            return $order->load('times');
        });

        return (new MedicationOrderResource($order))
            ->response()
            ->setStatusCode(201);
    }
}
