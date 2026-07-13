<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

class PurgeMarketplaceTrash extends Command
{
    protected $signature = 'marketplace:purge-trash';

    protected $description = 'Hapus permanen isi sampah toko/brand/produk yang sudah > 7 hari';

    public function handle(): int
    {
        // Urutan penting: produk dulu (melepas FK brand), lalu brand, lalu toko
        foreach ([Product::class, Brand::class, Store::class] as $model) {
            $purged = 0; $skipped = 0;

            $rows = $model::onlyTrashed()
                ->where('deleted_at', '<=', now()->subDays(7))
                ->get();

            foreach ($rows as $row) {
                try {
                    $row->forceDelete();
                    $purged++;
                } catch (QueryException) {
                    $skipped++; // masih direferensikan — biarkan di sampah
                }
            }

            $this->info(class_basename($model).": {$purged} dihapus permanen, {$skipped} dilewati.");
        }

        return self::SUCCESS;
    }
}
