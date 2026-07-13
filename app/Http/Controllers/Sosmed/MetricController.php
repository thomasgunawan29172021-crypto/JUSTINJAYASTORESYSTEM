<?php

namespace App\Http\Controllers\Sosmed;

use App\Http\Controllers\Controller;
use App\Models\SocialVideoPlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MetricController extends Controller
{
    /** Grid update massal: 1 baris = 1 posting (video × platform). Video beku dikecualikan. */
    public function index()
    {
        $postings = SocialVideoPlatform::with(['video.creators', 'platform', 'latestSnapshot'])
            ->whereHas('video', fn ($q) => $q->whereNull('frozen_at'))
            ->join('social_videos as sv', 'sv.id', '=', 'social_video_platform.social_video_id')
            ->orderBy('sv.published_at')       // tertua (paling due) dulu
            ->select('social_video_platform.*')
            ->paginate(50)
            ->withQueryString();

        return view('sosmed.metrics.index', ['postings' => $postings]);
    }

    /**
     * Simpan massal. Input: metrics[{posting_id}][views|likes|comments|saves].
     * Baris yang SEMUA kolomnya kosong = dilewati (bukan disimpan 0).
     * Video yang due (≥14 hari) dan salah satu posting-nya diupdate → dibekukan.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'metrics'                => ['required', 'array'],
            'metrics.*.views'        => ['nullable', 'integer', 'min:0'],
            'metrics.*.likes'        => ['nullable', 'integer', 'min:0'],
            'metrics.*.comments'     => ['nullable', 'integer', 'min:0'],
            'metrics.*.saves'        => ['nullable', 'integer', 'min:0'],
        ]);

        $saved = 0;
        $frozen = 0;

        DB::transaction(function () use ($data, $request, &$saved, &$frozen) {
            $postings = SocialVideoPlatform::with(['latestSnapshot', 'video'])
                ->whereHas('video', fn ($q) => $q->whereNull('frozen_at'))
                ->whereIn('id', array_keys($data['metrics']))
                ->get()->keyBy('id');

            $touchedVideos = collect();

            foreach ($data['metrics'] as $id => $m) {
                // Semua kosong → dilewati. Jangan diubah jadi 0 diam-diam.
                $filled = collect($m)->filter(fn ($v) => $v !== null && $v !== '');
                if ($filled->isEmpty()) continue;

                $posting = $postings[$id] ?? null;
                if (! $posting) continue;   // video sudah beku / posting tak ada → abaikan

                $last = $posting->latestSnapshot;

                $posting->snapshots()->create([
                    'views'       => $this->val($m, 'views', $last),
                    'likes'       => $this->val($m, 'likes', $last),
                    'comments'    => $this->val($m, 'comments', $last),
                    'saves'       => $this->val($m, 'saves', $last),
                    'recorded_by' => $request->user()->id,
                    'recorded_at' => now(),
                ]);
                $saved++;
                $touchedVideos->put($posting->video->id, $posting->video);
            }

            foreach ($touchedVideos as $video) {
                if ($video->isDue()) {
                    $video->update(['frozen_at' => now()]);
                    $frozen++;
                }
            }
        });

        return back()->with('ok', "{$saved} posting diperbarui" . ($frozen ? ", {$frozen} video dibekukan (final)" : '') . '.');
    }

    /** Refresh manual 1 posting (termasuk video yang sudah beku — buat kasus viral). */
    public function refresh(Request $request, SocialVideoPlatform $posting)
    {
        $m = $request->validate([
            'views'    => ['required', 'integer', 'min:0'],
            'likes'    => ['required', 'integer', 'min:0'],
            'comments' => ['required', 'integer', 'min:0'],
            'saves'    => ['required', 'integer', 'min:0'],
        ]);

        $posting->snapshots()->create([
            ...$m,
            'recorded_by' => $request->user()->id,
            'recorded_at' => now(),
        ]);

        return back()->with('ok', "Metrik {$posting->platform->name} — \"{$posting->video->title}\" diperbarui.");
    }

    /** Kolom kosong = pakai angka snapshot terakhir. Nol harus diketik eksplisit. */
    protected function val(array $m, string $field, ?\App\Models\VideoMetricSnapshot $last): int
    {
        $v = $m[$field] ?? null;
        if ($v !== null && $v !== '') {
            return (int) $v;
        }

        return (int) ($last?->{$field} ?? 0);
    }

    /** Hapus 1 pencatatan yang salah input. */
    public function destroySnapshot(\App\Models\VideoMetricSnapshot $snapshot)
    {
        abort_unless(request()->user()->role->isCeo(), 403, 'Hanya CEO yang bisa menghapus riwayat pencatatan.');

        $snapshot->delete();

        return back()->with('ok', 'Pencatatan metrik dihapus — angka kembali ke pencatatan sebelumnya.');
    }
}