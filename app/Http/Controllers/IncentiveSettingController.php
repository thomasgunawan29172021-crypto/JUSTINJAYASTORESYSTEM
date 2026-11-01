<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\IncentiveSubmission;
use App\Models\IncentiveTier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class IncentiveSettingController extends Controller
{
    public function index()
    {
        return view('incentives.settings', [
            'tiers' => IncentiveTier::with('branch')->orderBy('branch_id')->orderBy('currency')->orderBy('type')->orderBy('min_target')->get(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'], 'currency' => ['required', Rule::in(array_keys(IncentiveSubmission::CURRENCIES))],
            'type' => ['required', Rule::in(array_keys(IncentiveTier::TYPES))], 'label' => ['nullable', 'string', 'max:80'], 'min_target' => ['required', 'numeric', 'min:0'],
            'reward_value' => ['required', 'numeric', 'min:0'],
        ]);
        IncentiveTier::updateOrCreate(collect($data)->only(['branch_id', 'currency', 'type', 'min_target'])->all(), collect($data)->only(['label', 'reward_value'])->all());
        return back()->with('ok', 'Tingkat target insentif berhasil disimpan.');
    }

    public function destroy(IncentiveTier $tier)
    {
        $tier->delete();
        return back()->with('ok', 'Tingkat target dihapus.');
    }
}
