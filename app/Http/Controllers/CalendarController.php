<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\LeaveRequest;
use App\Models\MarketplaceTask;
use App\Models\ProductDiscount;
use App\Models\SocialVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CalendarController extends Controller
{
    /** Tugas posting dianggap "nginap" setelah sekian hari menunggu. */
    public const STALE_TASK_DAYS = 7;

    public function index(Request $request)
    {
        $month = $request->filled('month')
            ? Carbon::createFromFormat('Y-m', $request->string('month'))->startOfMonth()
            : now()->startOfMonth();
        $start = $month->copy()->startOfWeek(Carbon::MONDAY);
        $end   = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        /* ---------- Kumpulan event per tanggal: [Y-m-d => [event, ...]] ---------- */
        $events = collect();
        // $extra: manual, id, time, url, note — dipakai modal detail & link CEO.
        $push = function (string $date, string $label, string $colorClass, array $extra = []) use (&$events) {
            $events[$date] = ($events[$date] ?? collect())->push(array_merge([
                'label'  => $label,
                'color'  => $colorClass,
                'manual' => false,
                'id'     => null,
                'time'   => null,
                'url'    => null,
                'note'   => null,
            ], $extra));
        };

        // -- MANUAL --
        CalendarEvent::whereDate('date', '<=', $end)
            ->where(fn ($q) => $q->whereDate('date', '>=', $start)
                ->orWhereDate('date_end', '>=', $start))
            ->get()
            ->each(function ($e) use ($push, $start, $end) {
                $to = $e->date_end ?? $e->date;
                for ($d = $e->date->copy()->max($start); $d->lte($to) && $d->lte($end); $d->addDay()) {
                    $push($d->toDateString(), $e->title, CalendarEvent::COLORS[$e->color] ?? 'bg-slate-600', [
                        'manual' => true,
                        'id'     => $e->id,
                        'note'   => $e->note,
                    ]);
                }
            });

        // -- OTOMATIS: cuti/izin disetujui --
        LeaveRequest::with('user:id,name')
            ->where('status', \App\Enums\LeaveStatus::Approved->value)
            ->where('date_from', '<=', $end)->where('date_to', '>=', $start)
            ->get()
            ->each(function ($l) use ($push, $start, $end) {
                for ($d = $l->date_from->copy()->max($start); $d->lte($l->date_to) && $d->lte($end); $d->addDay()) {
                    $push($d->toDateString(), "🏖 {$l->user->name} — ".$l->type->label(), 'bg-sky-600', [
                        'url'  => route('leaves.manage'),
                        'note' => 'Cuti/izin '.$l->date_from->translatedFormat('d M').' – '.$l->date_to->translatedFormat('d M'),
                    ]);
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
                    $push($due->toDateString(), "⏰ Update metrik: {$v->title}".($pic ? " ({$pic->name})" : ''), 'bg-amber-500', [
                        'url'  => route('sosmed.videos.edit', $v),
                        'note' => 'Tayang '.$v->published_at->translatedFormat('d M').' — jatuh tempo pencatatan metrik.',
                    ]);
                }
            });

        // -- OTOMATIS: diskon berakhir (jam ikut, sejak diskon pakai datetime) --
        ProductDiscount::with('stores:id,name')
            ->whereBetween('ends_at', [$start, $end])
            ->get()
            ->each(fn ($dd) => $push($dd->ends_at->toDateString(),
                "🏷 Diskon berakhir: {$dd->name}"
                    .($dd->stores->isNotEmpty() ? ' — '.$dd->stores->pluck('name')->join(', ') : ''),
                'bg-rose-600',
                [
                    'time' => $dd->ends_at->format('H:i'),
                    'url'  => route('marketplace.discounts.index'),
                    'note' => $dd->typeLabel().($dd->note ? ' · '.$dd->note : ''),
                ]));

        // -- OTOMATIS: tugas posting nginap. Ini KONDISI SEKARANG, bukan peristiwa
        //    historis → ditaruh di HARI INI, bukan tanggal tugas dibuat. Digabung
        //    jadi satu event agar kalender tetap terbaca.
        if (today()->betweenIncluded($start, $end)) {
            $stale = MarketplaceTask::with(['product:id,name', 'store:id,name'])
                ->where('status', MarketplaceTask::STATUS_PENDING)
                ->where('type', MarketplaceTask::TYPE_POSTING)
                ->where('created_at', '<=', now()->subDays(self::STALE_TASK_DAYS))
                ->get();

            if ($stale->isNotEmpty()) {
                $push(today()->toDateString(),
                    "📌 {$stale->count()} tugas posting nginap ≥".self::STALE_TASK_DAYS." hari",
                    'bg-violet-600',
                    [
                        'url'  => route('marketplace.tasks.index'),
                        'note' => $stale->take(5)->map(fn ($t) => ($t->product?->name ?? '—').' @ '.($t->store?->name ?? '—'))->join('; ')
                                  .($stale->count() > 5 ? ' …+'.($stale->count() - 5).' lagi' : ''),
                    ]);
            }
        }

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