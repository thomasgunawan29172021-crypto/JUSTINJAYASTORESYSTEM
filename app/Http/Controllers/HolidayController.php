<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;

class HolidayController extends Controller
{
    public function index()
    {
        return view('holidays.index', [
            'holidays' => Holiday::orderBy('date')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'date' => ['required', 'date', 'unique:holidays,date'],
            'name' => ['required', 'string', 'max:150'],
        ]);

        Holiday::create($data);

        return back()->with('ok', 'Libur nasional ditambahkan.');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();

        return back()->with('ok', 'Libur nasional dihapus.');
    }
}