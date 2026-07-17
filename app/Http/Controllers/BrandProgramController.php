<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

/**
 * Program / subsidi supplier per brand — 2 tingkat, BERTINGKAT:
 *   10.000 −10% (depan) = 9.000, lalu −5% (belakang) dari 9.000 = 8.550
 *
 * Sengaja halaman sendiri (bukan di Edit Brand): Thomas ngisi ini sekaligus banyak
 * brand dalam sekali duduk, bukan satu-satu sambil ngedit nama.
 */
class BrandProgramController extends Controller
{
    public function index(Request $request)
    {
        return view('pricing.brand-programs', [
            // Sengaja TANPA paginate: ini form bulk, dan tombol simpan cuma ngirim
            // baris yang ketampil. Kalau dipaginasi, Thomas ngira udah kesimpen semua
            // padahal cuma halaman 1. Kalau brand udah ratusan, pakai search.
            'brands' => Brand::when($request->filled('q'),
                    fn ($q) => $q->where('name', 'like', '%'.trim($request->string('q')).'%'))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request)
    {
        // Normalisasi SEBELUM validate — form Indonesia ngirim "10,5" dan 'numeric'
        // nolak itu mentah-mentah.
        $normalized = [];
        foreach ((array) $request->input('programs', []) as $id => $values) {
            $normalized[$id] = [
                'front' => $this->parsePercent($values['front'] ?? null),
                'back'  => $this->parsePercent($values['back'] ?? null),
            ];
        }
        $request->merge(['programs' => $normalized]);

        $request->validate([
            'programs'         => ['array'],
            'programs.*.front' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'programs.*.back'  => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $input = $request->input('programs', []);

        // Ditarik SEKALI di luar loop — jangan Brand::find() di dalam foreach (N+1).
        $brands = Brand::whereIn('id', array_keys($input))->get()->keyBy('id');

        $saved = 0;

        foreach ($input as $id => $values) {
            $brand = $brands->get((int) $id);

            if (! $brand) {
                continue; // id dari form gak dipercaya mentah-mentah
            }

            // null tetap null = "gak ada program". Jangan ?? 0 — biar Thomas bisa
            // ngosongin lagi angka yang salah ketik.
            $brand->update([
                'program_front_percent' => $values['front'],
                'program_back_percent'  => $values['back'],
            ]);
            $saved++;
        }

        return back()->with('ok', "Program brand disimpan ({$saved} brand).");
    }

    /** "10,5" → "10.5". Kosong → null. */
    protected function parsePercent(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : str_replace(',', '.', $value);
    }
}
