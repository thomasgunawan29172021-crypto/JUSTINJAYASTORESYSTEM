<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\LeaveRequest;
use App\Models\ProductDiscount;
use App\Models\SocialVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth()
            : now()->startOfMonth();
        $start = $month->copy()->startOfWeek(Carbon::MONDAY);
        $end   = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        /* ---------- Kumpulan event per tanggal: [Y-m-d => [event, ...]] ---------- */
        $events = collect();
        $push = function (string $date, string $label, string $colorClass, bool $manual = false, ?int $id = null) use (&$events) {
            $events[$date] = ($events[$date] ?? collect())->push([
                'label' => $label, 'color' => $colorClass, 'manual' => $manual, 'id' => $id,
            ]);
        };

        // -- MANUAL --
        CalendarEvent::whereDate('date', '<=', $end)
            ->where(fn ($q) => $q->whereDate('date', '>=', $start)
                ->orWhereDate('date_end', '>=', $start))
            ->get()
            ->each(function ($e) use ($push, $start, $end) {
                $to = $e->date_end ?? $e->date;
                for ($d = $e->date->copy()->max($start); $d->lte($to) && $d->lte($end); $d->addDay()) {
                    $push($d->toDateString(), $e->title, CalendarEvent::COLORS[$e->color] ?? 'bg-slate-600', true, $e->id);
                }
            });

        // -- OTOMATIS: cuti/izin disetujui --
        LeaveRequest::with('user:id,name')
            ->where('status', \App\Enums\LeaveStatus::Approved->value)
            ->where('date_from', '<=', $end)->where('date_to', '>=', $start)
            ->get()
            ->each(function ($l) use ($push, $start, $end) {
                for ($d = $l->date_from->copy()->max($start); $d->lte($l->date_to) && $d->lte($end); $d->addDay()) {
                    $push($d->toDateString(), "🏖 {$l->user->name} — ".$l->type->label(), 'bg-sky-600');
                }
            });

        // -- OTOMATIS: video sosmed due update (hari ke-14) --
        SocialVideo::active()->with('creators:id,name')
            ->whereBetween('published_at', [$start->copy()->subDays(SocialVideo::DUE_DAYS), $end])
            ->get()
            ->each(function ($v) use ($push, $start, $end) {
                $due = $v->published_at->copy()->addDays(SocialVideo::DUE_DAYS);
                if ($due->betweenIncluded($start, $end)) {
                    $pic = $v->creators->firstWhere('pivot.is_pic', true);
                    $push($due->toDateString(), "⏰ Update metrik: {$v->title}".($pic ? " ({$pic->name})" : ''), 'bg-amber-500');
                }
            });

        // -- OTOMATIS: diskon berakhir --
        ProductDiscount::with('product:id,name')
            ->whereBetween('ends_at', [$start, $end])
            ->get()
            ->each(fn ($dd) => $push($dd->ends_at->toDateString(),
                "🏷 Diskon berakhir: {$dd->product->name}", 'bg-rose-600'));

        /* ---------- Grid minggu ---------- */
        $weeks = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $weeks[$d->format('o-W')][] = $d->copy();
        }

        return view('calendar.index', [
            'month'  => $month,
            'weeks'  => array_values($weeks),
            'events' => $events,
            'isCeo'  => $request->user()->role->isCeo(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'    => ['required', 'string', 'max:150'],
            'date'     => ['required', 'date'],
            'date_end' => ['nullable', 'date', 'after_or_equal:date'],
            'color'    => ['required', 'in:'.implode(',', array_keys(CalendarEvent::COLORS))],
            'note'     => ['nullable', 'string', 'max:300'],
        ]);

        CalendarEvent::create([...$data, 'created_by' => $request->user()->id]);

        return back()->with('ok', "Event \"{$data['title']}\" ditambahkan.");
    }

    public function destroy(CalendarEvent $event)
    {
        $event->delete();

        return back()->with('ok', 'Event dihapus.');
    }
}