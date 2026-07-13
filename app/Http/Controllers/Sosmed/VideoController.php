<?php

namespace App\Http\Controllers\Sosmed;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use App\Models\SocialVideo;
use App\Models\SocialVideoPlatform;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class VideoController extends Controller
{
    public function index(Request $request)
    {
        $videos = SocialVideo::with(['creators', 'postings.platform', 'postings.latestSnapshot'])
            ->when($request->filled('platform_id'), fn ($q) => $q->whereHas('postings',
                fn ($p) => $p->where('platform_id', (int) $request->input('platform_id'))))
            ->when($request->filled('user_id'), fn ($q) => $q->whereHas('creators',
                fn ($c) => $c->where('users.id', (int) $request->input('user_id'))))
            ->orderByDesc('published_at')
            ->paginate(30)
            ->withQueryString();

        return view('sosmed.videos.index', [
            'videos'    => $videos,
            'staff'     => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'platforms' => Platform::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('sosmed.videos.create', [
            'staff'     => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'platforms' => Platform::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $video = SocialVideo::create([
            'is_collab'    => (bool) ($data['is_collab'] ?? false),
            'title'        => $data['title'],
            'theme'        => $data['theme'] ?? null,
            'published_at' => $data['published_at'],
            'added_by'     => $request->user()->id,
        ]);

        $this->syncCreators($video, $data);

        foreach ($data['urls'] as $pid => $url) {
            $video->postings()->create(['platform_id' => $pid, 'url' => $url]);
        }

        return redirect()->route('sosmed.videos.index')->with('ok', "Video \"{$video->title}\" tercatat.");
    }

    public function edit(SocialVideo $video)
    {
        $video->load(['creators', 'postings.platform', 'postings.snapshots.recorder', 'postings.latestSnapshot']);

        return view('sosmed.videos.edit', [
            'video'       => $video,
            'staff'       => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'platforms'   => Platform::orderBy('name')->get(),
            'picId'       => $video->creators->firstWhere('pivot.is_pic', true)?->id,
            'memberIds'   => $video->creators->where('pivot.is_pic', false)->pluck('id')->all(),
            'postingUrls' => $video->postings->pluck('url', 'platform_id')->all(),
        ]);
    }

    public function update(Request $request, SocialVideo $video)
    {
        $data = $this->validated($request, $video->id);

        $video->update([
            'is_collab'    => (bool) ($data['is_collab'] ?? false),
            'title'        => $data['title'],
            'theme'        => $data['theme'] ?? null,
            'published_at' => $data['published_at'],
        ]);

        $this->syncCreators($video, $data);

        // Sync posting: hapus platform yang di-uncheck, update url yang berubah, tambah yang baru.
        $video->postings()->whereNotIn('platform_id', array_keys($data['urls']))->get()
            ->each->delete(); // cascade hapus snapshot platform itu — disengaja: platform dicabut = riwayatnya ikut
        foreach ($data['urls'] as $pid => $url) {
            $video->postings()->updateOrCreate(['platform_id' => $pid], ['url' => $url]);
        }

        return redirect()->route('sosmed.videos.index')->with('ok', 'Video diperbarui.');
    }

    public function destroy(SocialVideo $video)
    {
        $video->delete(); // soft delete

        return back()->with('ok', "Video \"{$video->title}\" dipindah ke sampah.");
    }

    /* ---------------- helper ---------------- */

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'is_collab'      => ['nullable', 'boolean'],
            'pic_id'         => ['required', 'exists:users,id'],
            'member_ids'     => ['exclude_unless:is_collab,1', 'required', 'array', 'min:1'],
            'member_ids.*'   => ['exists:users,id', 'different:pic_id'],
            'platform_ids'   => ['required', 'array', 'min:1'],
            'platform_ids.*' => ['exists:platforms,id'],
            'urls'           => ['required', 'array'],
            'title'          => ['required', 'string', 'max:200'],
            'theme'          => ['nullable', 'string', 'max:100'],
            'published_at'   => ['required', 'date', 'before_or_equal:today'],
        ]);

        // Per platform tercentang: link wajib, sesuai domain, dan belum dipakai video lain.
        $platforms = Platform::whereIn('id', $data['platform_ids'])->get()->keyBy('id');
        $urls = [];
        foreach ($data['platform_ids'] as $pid) {
            $url = trim($data['urls'][$pid] ?? '');
            $p   = $platforms[$pid];

            if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                throw ValidationException::withMessages(['urls' => "Link untuk {$p->name} wajib diisi dan valid."]);
            }
            if (! $p->acceptsUrl($url)) {
                throw ValidationException::withMessages(['urls' => "Link tidak sesuai domain platform {$p->name}."]);
            }
            $taken = SocialVideoPlatform::where('url', $url)
                ->when($ignoreId, fn ($q) => $q->where('social_video_id', '!=', $ignoreId))
                ->exists();
            if ($taken) {
                throw ValidationException::withMessages(['urls' => "Link {$p->name} sudah terdaftar di video lain."]);
            }
            $urls[$pid] = $url;
        }
        $data['urls'] = $urls;

        return $data;
    }

    /** Susun pivot: PIC selalu 1 (is_pic=true); anggota hanya kalau colab. */
    protected function syncCreators(SocialVideo $video, array $data): void
    {
        $pivot = [$data['pic_id'] => ['is_pic' => true]];

        if (! empty($data['is_collab'])) {
            foreach ($data['member_ids'] ?? [] as $id) {
                $pivot[(int) $id] = ['is_pic' => false];
            }
        }

        $video->creators()->sync($pivot);
    }
}
