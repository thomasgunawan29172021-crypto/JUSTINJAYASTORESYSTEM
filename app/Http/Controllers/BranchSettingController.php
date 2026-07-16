<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class BranchSettingController extends Controller
{
    public function index()
    {
        // withCount: dipakai buat nampilkan "terikat X karyawan" tanpa N+1 per kartu.
        return view('branches.index', [
            'branches' => Branch::withCount('users')->orderBy('code')->get(),
        ]);
    }

    public function create()
    {
        return view('branches.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'              => ['required', 'string', 'max:10', 'unique:branches,code'],
            'name'              => ['required', 'string', 'max:100'],
            'address'           => ['nullable', 'string', 'max:200'],
            'phone'             => ['nullable', 'string', 'max:30'],
            'latitude'          => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'         => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_m' => ['required', 'integer', 'min:20', 'max:1000'],
        ]);

        $data['code']        = strtoupper(trim($data['code']));
        $data['has_service'] = $request->boolean('has_service');

        $branch = Branch::create($data);

        return redirect()->route('branches.index')
            ->with('ok', "Cabang {$branch->name} ({$branch->code}) ditambahkan. Jangan lupa isi koordinat & radius sebelum dipakai absensi.");
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

        $data['has_service'] = $request->boolean('has_service');

        $branch->update($data);

        return back()->with('ok', "Cabang {$branch->name} diperbarui.");
    }

    /**
     * Hapus cabang — DITOLAK selama masih ada data terikat, dan CEO diberi tahu
     * persis apa yang mengikat supaya tahu harus beresin apa dulu.
     */
    public function destroy(Branch $branch)
    {
        $deps = array_filter([
            'karyawan'     => User::where('branch_id', $branch->id)->count(),
            'tiket servis' => \App\Models\ServiceTicket::where('branch_id', $branch->id)->count(),
        ]);

        if ($deps) {
            $detail = collect($deps)->map(fn ($n, $label) => "{$n} {$label}")->join(' · ');

            return back()->withErrors([
                'branch' => "Cabang {$branch->name} tidak bisa dihapus — masih terikat: {$detail}. Pindahkan atau hapus data tersebut dulu.",
            ]);
        }

        try {
            $branch->delete();
        } catch (QueryException) {
            // Jaring pengaman: ada relasi lain di luar daftar $deps di atas.
            return back()->withErrors([
                'branch' => "Cabang {$branch->name} masih direferensikan data lain — tidak bisa dihapus.",
            ]);
        }

        return back()->with('ok', "Cabang {$branch->name} dihapus.");
    }
}
