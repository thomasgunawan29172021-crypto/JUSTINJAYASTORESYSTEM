<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Cabang (data asli, aman) ----
        $utama = Branch::updateOrCreate(['code' => 'ILR'], [
            'name'              => 'Cabang Utama (15 Ilir)',
            'address'           => 'Jl. Segaran, 14 Ilir, Kec. Ilir Tim. I, Kota Palembang, Sumatera Selatan 30111',
            'phone'             => '081288807388',
            'latitude'          => -2.9817178711209524,
            'longitude'         => 104.7647537748618,
            'geofence_radius_m' => 100,
            'has_service'       => true,
        ]);

        Branch::updateOrCreate(['code' => 'KM9'], [
            'name'              => 'Cabang KM 9',
            'address'           => 'Jl. Pengadilan Tinggi No.Km.9, Karya Baru, Kec. Alang-Alang Lebar, Kota Palembang, Sumatera Selatan 30961',
            'phone'             => '081288807388',
            'latitude'          => -2.9265712863732247,
            'longitude'         => 104.71552335101957,
            'geofence_radius_m' => 100,
            'has_service'       => true,
        ]);

        // ---- Platform sosmed default ----
        foreach ([
            ['TikTok', 'tiktok.com'],
            ['Facebook', 'facebook.com,fb.watch'],
            ['YouTube', 'youtube.com,youtu.be'],
            ['Instagram', 'instagram.com'],
        ] as [$name, $domains]) {
            Platform::updateOrCreate(['name' => $name], ['domains' => $domains]);
        }

        // ---- Akun CEO Thomas ----
        // Password diambil dari ENV, BUKAN hardcode. Set CEO_PASSWORD di dashboard Laravel Cloud.
        $password = env('CEO_PASSWORD');
        if (! $password) {
            $this->command->error('CEO_PASSWORD belum di-set di environment. Seeder dihentikan.');
            return;
        }

        User::updateOrCreate(
            ['email' => env('CEO_EMAIL', 'thomas@justinjaya.com')],
            [
                'name'      => 'Thomas',
                'password'  => Hash::make($password),
                'role'      => UserRole::Ceo,
                'branch_id' => $utama->id,
                'is_active' => true,
            ]
        );
    }
}