<?php

namespace App\Services;

use App\Models\MarketplaceTask;
use App\Models\Posting;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;

class PostingTaskService
{
    /** Toko target sebuah produk = pemetaan brand→toko (aturan Thomas), hanya toko aktif. */
    public function targetStores(Product $product)
    {
        return $product->brand->stores()->where('is_active', true)->get();
    }

    /**
     * Produk baru → tugas posting per toko target.
     * $alreadyPostedStoreIds = input mundur: ditandai sudah posting, TANPA tugas.
     *
     * @return array{0:int,1:int} [tugas dibuat, toko ditandai sudah posting]
     */
    public function generateForNewProduct(Product $product, array $alreadyPostedStoreIds = [], ?User $by = null): array
    {
        $created = 0;
        $marked  = 0;

        foreach ($this->targetStores($product) as $store) {
            if (in_array($store->id, $alreadyPostedStoreIds, true)) {
                Posting::firstOrCreate(
                    ['product_id' => $product->id, 'store_id' => $store->id],
                    ['posted_by' => $by?->id, 'posted_at' => now()]
                );
                $marked++;
            } else {
                MarketplaceTask::firstOrCreate(
                    [
                        'type'       => MarketplaceTask::TYPE_POSTING,
                        'product_id' => $product->id,
                        'store_id'   => $store->id,
                        'status'     => MarketplaceTask::STATUS_PENDING,
                    ],
                    ['created_at' => now()]
                );
                $created++;
            }
        }

        return [$created, $marked];
    }

    /**
     * Harga marketplace berubah → tugas update harga HANYA untuk toko yang
     * SUDAH posting produk itu, di marketplace yang harganya berubah.
     * firstOrCreate = dedupe: perubahan beruntun tetap 1 tugas pending.
     */
    public function generateForPriceChange(Product $product, array $changedMarketplaces): int
    {
        if ($changedMarketplaces === []) {
            return 0;
        }

        $postedStoreIds = Posting::where('product_id', $product->id)->pluck('store_id');

        $stores = Store::whereIn('id', $postedStoreIds)
            ->whereIn('marketplace', $changedMarketplaces)
            ->where('is_active', true)
            ->get();

        $count = 0;
        foreach ($stores as $store) {
            MarketplaceTask::firstOrCreate(
                [
                    'type'       => MarketplaceTask::TYPE_PRICE_UPDATE,
                    'product_id' => $product->id,
                    'store_id'   => $store->id,
                    'status'     => MarketplaceTask::STATUS_PENDING,
                ],
                ['created_at' => now(), 'note' => 'Harga '.$store->marketplace.' berubah']
            );
            $count++;
        }

        return $count;
    }
}
