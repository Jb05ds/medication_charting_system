<?php

namespace App\Http\Controllers;

use App\Http\Resources\MedicationOrderResource;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
