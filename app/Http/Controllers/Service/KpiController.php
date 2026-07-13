<?php

namespace App\Http\Controllers\Service;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\KpiService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class KpiController extends Controller
{
    public function index(Request $request, KpiService $kpi)
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from'))->startOfDay()
            : now()->startOfMonth();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to'))->endOfDay()
            : now()->endOfDay();

        $branchId = $request->filled('branch_id') ? (int) $request->input('branch_id') : null;

        return view('service.kpi', [
            'data'     => $kpi->build($from, $to, $branchId),
            'from'     => $from,
            'to'       => $to,
            'branchId' => $branchId,
            'branches' => Branch::where('has_service', true)->get(),
        ]);
    }
}