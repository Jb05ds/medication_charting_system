<?php

namespace App\Http\Controllers;

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
}
