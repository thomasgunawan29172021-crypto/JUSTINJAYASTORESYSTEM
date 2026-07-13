<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchSettingController extends Controller
{
    public function index()
    {
        return view('branches.index', ['branches' => Branch::all()]);
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'address'           => ['nullable', 'string', 'max:200'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'latitude'          => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'         => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_m' => ['required', 'integer', 'min:20', 'max:1000'],
        ]);

        $branch->update($data);

        return back()->with('ok', "Cabang {$branch->name} diperbarui.");
    }
}