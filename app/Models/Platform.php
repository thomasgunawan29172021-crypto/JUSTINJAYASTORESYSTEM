<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Platform extends Model
{
    protected $fillable = ['name', 'domains'];

    public function postings() { return $this->hasMany(SocialVideoPlatform::class); }

    /** @return string[] */
    public function domainList(): array
    {
        return $this->domains
            ? array_filter(array_map('trim', explode(',', $this->domains)))
            : [];
    }

    public function acceptsUrl(string $url): bool
    {
        $list = $this->domainList();
        if (empty($list)) return true; // platform tanpa domain = bebas
        $host = parse_url($url, PHP_URL_HOST) ?: '';
        foreach ($list as $d) {
            if (str_ends_with($host, $d)) return true;
        }
        return false;
    }
}