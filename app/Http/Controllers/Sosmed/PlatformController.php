<?php

namespace App\Http\Controllers\Sosmed;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use App\Models\SocialVideo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlatformController extends Controller
{
    public function index()
    {
        return view('sosmed.platforms.index', [
            'platforms' => Platform::withCount('postings')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Platform::create($data);

        return back()->with('ok', "Platform \"{$data['name']}\" ditambahkan.");
    }

    public function update(Request $request, Platform $platform)
    {
        $data = $this->validated($request, $platform->id);
        $platform->update($data);

        return back()->with('ok', "Platform \"{$platform->name}\" diperbarui.");
    }

    public function destroy(Platform $platform)
    {
        // PROTEKSI: platform yang masih dipakai posting tidak boleh dihapus.
        $videos = SocialVideo::whereHas('postings',
                fn ($q) => $q->where('platform_id', $platform->id))
            ->with(['postings' => fn ($q) => $q->where('platform_id', $platform->id)])
            ->orderByDesc('published_at')
            ->get();

        if ($videos->isNotEmpty()) {
            return back()->with('blockedDelete', [
                'platform' => $platform->name,
                'videos'   => $videos->map(fn ($v) => [
                    'id'    => $v->id,
                    'title' => $v->title,
                    'date'  => $v->published_at->translatedFormat('d M Y'),
                    'url'   => route('sosmed.videos.edit', $v),
                ])->all(),
            ]);
        }

        $name = $platform->name;
        $platform->delete();

        return back()->with('ok', "Platform \"{$name}\" dihapus.");
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'    => ['required', 'string', 'max:50', Rule::unique('platforms', 'name')->ignore($ignoreId)],
            'domains' => ['nullable', 'string', 'max:300', 'regex:/^[a-z0-9.,\-\s]+$/i'],
        ], [
            'domains.regex' => 'Domain dipisah koma, contoh: youtube.com,youtu.be — tanpa https:// dan tanpa garis miring.',
        ]);
    }
}
