<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        return view('users.index', [
            'users' => User::with(['branch', 'branches'])->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('users.create', [
            'roles'    => UserRole::cases(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $selectedBranchIds = $this->requestedBranchIds($request);

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            // 'string' bukan 'email' — konsisten dengan akun seed (admin@admin tanpa TLD)
            'email'        => ['required', 'string', 'max:100', 'unique:users,email'],
            'phone'        => ['nullable', 'string', 'max:20'],
            'password'     => ['required', 'string', 'min:6'],
            'role'         => ['required', Rule::enum(UserRole::class)],
            // extra_roles cuma NAMBAH hak akses. CEO dilarang jadi role tambahan.
            'extra_roles'   => ['nullable', 'array'],
            'extra_roles.*' => ['string', Rule::enum(UserRole::class), Rule::notIn([UserRole::Ceo->value])],
            // branch_id tetap menjadi cabang utama agar fitur lama tetap kompatibel.
            'branch_id'    => ['required', 'integer', 'exists:branches,id', Rule::in($selectedBranchIds)],
            // Semua cabang yang boleh digunakan untuk absensi.
            'branch_ids'   => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['required', 'integer', 'distinct', 'exists:branches,id'],
            'base_salary'  => ['nullable', 'integer', 'min:0'],
        ], [
            'branch_id.in'          => 'Cabang utama harus termasuk cabang absensi yang dicentang.',
            'branch_ids.required'   => 'Pilih minimal satu cabang absensi.',
            'branch_ids.min'        => 'Pilih minimal satu cabang absensi.',
            'extra_roles.*.not_in'  => 'CEO tidak bisa dijadikan role tambahan.',
        ]);

        $branchIds = collect($data['branch_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        unset($data['branch_ids']);

        $data['is_active']   = $request->boolean('is_active');
        $data['base_salary'] = $data['base_salary'] ?? 0;
        $data['extra_roles'] = $this->sanitizeExtraRoles($data['extra_roles'] ?? [], $data['role']);

        $user = DB::transaction(function () use ($data, $branchIds) {
            // Password otomatis di-hash oleh cast 'hashed' di model User.
            $user = User::create($data);
            $user->branches()->sync($branchIds);

            return $user;
        });

        return redirect()->route('users.index')->with('ok', "Akun {$user->name} dibuat.");
    }

    public function edit(User $user)
    {
        $user->load('branches');

        return view('users.edit', [
            'user'     => $user,
            'roles'    => UserRole::cases(),
            'branches' => Branch::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $selectedBranchIds = $this->requestedBranchIds($request);

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:100'],
            'email'        => ['required', 'string', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'        => ['nullable', 'string', 'max:20'],
            'password'     => ['nullable', 'string', 'min:6'], // kosong = password tidak diubah
            'role'         => ['required', Rule::enum(UserRole::class)],
            // extra_roles cuma NAMBAH hak akses. CEO dilarang jadi role tambahan.
            'extra_roles'   => ['nullable', 'array'],
            'extra_roles.*' => ['string', Rule::enum(UserRole::class), Rule::notIn([UserRole::Ceo->value])],
            'branch_id'    => ['required', 'integer', 'exists:branches,id', Rule::in($selectedBranchIds)],
            'branch_ids'   => ['required', 'array', 'min:1'],
            'branch_ids.*' => ['required', 'integer', 'distinct', 'exists:branches,id'],
            'base_salary'  => ['nullable', 'integer', 'min:0'],
        ], [
            'branch_id.in'          => 'Cabang utama harus termasuk cabang absensi yang dicentang.',
            'branch_ids.required'   => 'Pilih minimal satu cabang absensi.',
            'branch_ids.min'        => 'Pilih minimal satu cabang absensi.',
            'extra_roles.*.not_in'  => 'CEO tidak bisa dijadikan role tambahan.',
        ]);

        $branchIds = collect($data['branch_ids'])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        unset($data['branch_ids']);

        $data['is_active']   = $request->boolean('is_active');
        $data['extra_roles'] = $this->sanitizeExtraRoles($data['extra_roles'] ?? [], $data['role']);

        // Anti kunci diri sendiri: CEO tidak boleh menonaktifkan
        // atau menurunkan role akunnya yang sedang dipakai.
        if ($user->id === $request->user()->id) {
            if (! $data['is_active'] || $data['role'] !== UserRole::Ceo->value) {
                return back()->withInput()->withErrors([
                    'role' => 'Tidak bisa menonaktifkan atau mengubah role akun yang sedang Anda pakai.',
                ]);
            }
        }

        if (blank($data['password'])) {
            unset($data['password']);
        }

        if (blank($data['base_salary'] ?? null)) {
            unset($data['base_salary']);
        }

        DB::transaction(function () use ($user, $data, $branchIds) {
            $user->update($data);
            $user->branches()->sync($branchIds);
        });

        return redirect()->route('users.index')->with('ok', "Akun {$user->name} diperbarui.");
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Tidak bisa menghapus akun sendiri.']);
        }

        try {
            $user->delete();
        } catch (QueryException) {
            // FK constraint: user sudah tercatat di tiket/riwayat — data historis tidak boleh yatim.
            return back()->withErrors([
                'user' => "Akun {$user->name} tidak bisa dihapus karena sudah tercatat di riwayat tiket. Gunakan Nonaktifkan.",
            ]);
        }

        return redirect()->route('users.index')->with('ok', "Akun {$user->name} dihapus.");
    }

    /** @return array<int> ID cabang absensi dari checkbox form. */
    protected function requestedBranchIds(Request $request): array
    {
        return collect((array) $request->input('branch_ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Bersihkan role tambahan sebelum disimpan:
     * - buang CEO (belt-and-suspenders — validasi sudah menolak, ini jaga kalau lolos),
     * - buang duplikat jabatan utama (mubazir; union sudah menyertakannya),
     * - dedup, dan simpan null kalau kosong biar kolomnya rapi.
     *
     * @param  array<int, string>  $extras
     * @return array<int, string>|null
     */
    protected function sanitizeExtraRoles(array $extras, string $primaryRole): ?array
    {
        $clean = collect($extras)
            ->map(fn ($r) => (string) $r)
            ->reject(fn ($r) => $r === UserRole::Ceo->value)
            ->reject(fn ($r) => $r === $primaryRole)
            ->unique()
            ->values()
            ->all();

        return $clean ?: null;
    }
}
