<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        return view('users.index', [
            'users' => User::with('branch')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('users.create', [
            'roles'    => UserRole::cases(),
            'branches' => Branch::all(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            // 'string' bukan 'email' — konsisten dengan akun seed (admin@admin tanpa TLD)
            'email'     => ['required', 'string', 'max:100', 'unique:users,email'],
            'password'  => ['required', 'string', 'min:6'],
            'role'      => ['required', Rule::enum(UserRole::class)],
            'branch_id' => ['required', 'exists:branches,id'], // wajib di form; DB tetap nullable (jaring pengaman)
            'base_salary' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['base_salary'] = $data['base_salary'] ?? 0;

        // Password otomatis di-hash oleh cast 'hashed' di model User
        $user = User::create($data);

        return redirect()->route('users.index')->with('ok', "Akun {$user->name} dibuat.");
    }

    public function edit(User $user)
    {
        return view('users.edit', [
            'user'     => $user,
            'roles'    => UserRole::cases(),
            'branches' => Branch::all(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'email'     => ['required', 'string', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'password'  => ['nullable', 'string', 'min:6'], // kosong = password tidak diubah
            'role'      => ['required', Rule::enum(UserRole::class)],
            'branch_id' => ['required', 'exists:branches,id'],
            'base_salary' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

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

        $user->update($data);

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
            // FK constraint: user sudah tercatat di tiket/riwayat — data historis tidak boleh yatim
            return back()->withErrors([
                'user' => "Akun {$user->name} tidak bisa dihapus karena sudah tercatat di riwayat tiket. Gunakan Nonaktifkan.",
            ]);
        }

        return redirect()->route('users.index')->with('ok', "Akun {$user->name} dihapus.");
    }
}