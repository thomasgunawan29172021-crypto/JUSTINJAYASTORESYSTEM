<?php

namespace App\Http\Controllers;

use App\Models\Payslip;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\Request;
use RuntimeException;

class PayrollController extends Controller
{
    /** Daftar karyawan + status slip untuk 1 periode. */
    public function index(Request $request, PayrollService $payroll)
    {
        $period = $request->input('period', now()->subMonth()->format('Y-m'));

        $rows = User::with('workSchedule')
            ->where('is_active', true)->orderBy('name')->get()
            ->map(function ($u) use ($period, $payroll) {
                $slip = Payslip::where('user_id', $u->id)->where('period', $period)->first();

                $draft = null;
                $error = null;
                if (! $slip) {
                    try {
                        $draft = $payroll->calculate($u, $period);
                    } catch (RuntimeException $e) {
                        $error = $e->getMessage();
                    }
                }

                return ['user' => $u, 'slip' => $slip, 'draft' => $draft, 'error' => $error];
            });

        return view('payroll.index', ['rows' => $rows, 'period' => $period]);
    }

    public function issue(Request $request, User $user, PayrollService $payroll)
    {
        $period = $request->validate(['period' => ['required', 'date_format:Y-m']])['period'];

        try {
            $slip = $payroll->issue($user, $period, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['payroll' => $e->getMessage()]);
        }

        return back()->with('ok', "Slip {$user->name} periode {$period} terbit — netto Rp ".number_format($slip->net_salary, 0, ',', '.'));
    }

    public function show(Payslip $payslip)
    {
        $payslip->load(['user.branch', 'issuer']);

        return view('payroll.show', ['slip' => $payslip]);
    }
}