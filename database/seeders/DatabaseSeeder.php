<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Master yang berdiri sendiri (tidak bergantung data di bawah).
        $this->call([
            ProcessStageSeeder::class,
            SupplierSeeder::class,
        ]);

        // ── Users ─────────────────────────────────────────────
        $uid = DB::table('users')->insertGetId([
            'name' => 'Admin FishPro', 'email' => 'admin@fishpro.com',
            'password' => Hash::make('password'), 'email_verified_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Material Groups ────────────────────────────────────
        $mg = [];
        foreach ([
            ['YF LOIN',   'kg', '#2563eb', 'Yellow Fin Loin'],
            ['BE LOIN',   'kg', '#16a34a', 'Big Eye Loin'],
            ['Ayam Beku', 'kg', '#f59e0b', 'Ayam beku fresh'],
            ['Cumi',      'kg', '#dc2626', 'Cephalopod'],
        ] as [$name,$unit,$color,$desc]) {
            $mg[$name] = DB::table('material_groups')->insertGetId([
                'name'=>$name,'unit'=>$unit,'color'=>$color,
                'description'=>$desc,'is_active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }

        // ── Material Subgroups ─────────────────────────────────
        $ms = [];
        // `code` menjadi komponen Batch Number Induk (RM-260918-NJY-{YFW}-001),
        // jadi sengaja diisi singkatan yang mudah dikenali operator.
        $subgroupDefs = [
            'YF LOIN' => [['YF LOIN EROPA WARNA','YFW'],['YF LOIN EROPA SASI','YFS']],
            'BE LOIN' => [['BE LOIN EROPA KASAR','BEK'],['BE BAHAN BAKU LOIN FRESH','BEF']],
            'Ayam Beku' => [['Ayam Boiler','AYB'],['Ayam Kampung','AYK']],
            'Cumi' => [['Cumi Segar','CMS'],['Cumi Beku','CMB']],
        ];
        foreach ($subgroupDefs as $gName => $subs) {
            foreach ($subs as [$sName, $sCode]) {
                $id = DB::table('material_subgroups')->insertGetId([
                    'material_group_id'=>$mg[$gName],'name'=>$sName,'code'=>$sCode,
                    'is_active'=>true,'created_at'=>now(),'updated_at'=>now(),
                ]);
                $ms[$sName] = $id;
                DB::table('raw_material_stocks')->insert([
                    'material_subgroup_id'=>$id,'current_stock_kg'=>0,
                    'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
        }

        // ── Product Groups ─────────────────────────────────────
        $pg = [];
        foreach ([
            ['Ikan',          '#3b82f6'],
            ['Ayam',          '#f59e0b'],
            ['Seafood',       '#06b6d4'],
            ['Frozen Food',   '#8b5cf6'],
            ['Ready To Cook', '#ec4899'],
        ] as [$name,$color]) {
            $pg[$name] = DB::table('product_groups')->insertGetId([
                'name'=>$name,'color'=>$color,
                'is_active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }

        // ── Product Types ──────────────────────────────────────
        $pt = [];
        // `code` menjadi komponen kode box packing (BOX-PO2024001-{YFSTK}-0187).
        $productDefs = [
            'Ikan'  => [
                ['YF STEAK','YFSTK','#2563eb'],['YF LOIN','YFLN','#1d4ed8'],
                ['BE STEAK','BESTK','#16a34a'],['BE LOIN','BELN','#15803d'],
                ['Saute YF','SYF','#60a5fa'],['BE Saute','SBE','#4ade80'],
                ['Serbuk','SRBK','#6b7280'],
            ],
            'Ayam'  => [
                ['Dada Ayam','DADA','#f59e0b'],['Paha Ayam','PAHA','#d97706'],
                ['Sayap Ayam','SAYAP','#b45309'],['Tulang Ayam','TLNG','#92400e'],
            ],
            'Seafood' => [
                ['Ring Cumi','RCUMI','#dc2626'],['Calamari','CALA','#ef4444'],
            ],
            'Frozen Food' => [
                ['Nugget Ikan','NGGT','#8b5cf6'],
            ],
            'Ready To Cook' => [
                ['Ikan Bumbu','IKBMB','#ec4899'],
            ],
        ];
        foreach ($productDefs as $gName => $products) {
            foreach ($products as [$name,$code,$color]) {
                $pt[$name] = DB::table('product_types')->insertGetId([
                    'product_group_id'=>$pg[$gName],'name'=>$name,'code'=>$code,'color'=>$color,
                    'is_active'=>true,'created_at'=>now(),'updated_at'=>now(),
                ]);
            }
        }

        // ── Yield Estimates ────────────────────────────────────
        // YF LOIN EROPA WARNA → produk ikan
        $yieldDefs = [
            'YF LOIN EROPA WARNA' => [
                ['YF STEAK', 35], ['YF LOIN', 30], ['Saute YF', 20], ['Serbuk', 15],
            ],
            'YF LOIN EROPA SASI' => [
                ['YF LOIN', 40], ['Saute YF', 35], ['Serbuk', 25],
            ],
            'BE LOIN EROPA KASAR' => [
                ['BE STEAK', 35], ['BE LOIN', 30], ['BE Saute', 25], ['Serbuk', 10],
            ],
            'BE BAHAN BAKU LOIN FRESH' => [
                ['BE LOIN', 45], ['BE Saute', 40], ['Serbuk', 15],
            ],
            'Ayam Boiler' => [
                ['Dada Ayam', 40], ['Paha Ayam', 32], ['Sayap Ayam', 12], ['Tulang Ayam', 16],
            ],
            'Ayam Kampung' => [
                ['Dada Ayam', 38], ['Paha Ayam', 30], ['Sayap Ayam', 14], ['Tulang Ayam', 18],
            ],
        ];
        foreach ($yieldDefs as $subName => $yields) {
            if (!isset($ms[$subName])) continue;
            foreach ($yields as [$ptName, $pct]) {
                if (!isset($pt[$ptName])) continue;
                DB::table('yield_estimates')->insert([
                    'material_subgroup_id' => $ms[$subName],
                    'product_type_id'      => $pt[$ptName],
                    'percentage'           => $pct,
                    'created_at'           => now(), 'updated_at' => now(),
                ]);
            }
        }

        // ── Purchase Orders ────────────────────────────────────
        $po1 = DB::table('purchase_orders')->insertGetId([
            'po_number'=>'PO-2024-001','buyer_name'=>'PT Seafood Nusantara',
            'order_date'=>Carbon::today()->subDays(10),'deadline'=>Carbon::today()->addDays(5),
            'status'=>'in_progress','created_by'=>$uid,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $po2 = DB::table('purchase_orders')->insertGetId([
            'po_number'=>'PO-2024-002','buyer_name'=>'CV Maju Bersama',
            'order_date'=>Carbon::today()->subDays(3),'deadline'=>Carbon::today()->addDays(14),
            'status'=>'active','created_by'=>$uid,'created_at'=>now(),'updated_at'=>now(),
        ]);

        // ── PO Items ───────────────────────────────────────────
        DB::table('po_items')->insert([
            ['purchase_order_id'=>$po1,'product_type_id'=>$pt['Saute YF'],
             'target_weight_kg'=>400,'target_box'=>20,'weight_per_box_kg'=>20,'created_at'=>now(),'updated_at'=>now()],
            ['purchase_order_id'=>$po1,'product_type_id'=>$pt['BE Saute'],
             'target_weight_kg'=>200,'target_box'=>20,'weight_per_box_kg'=>10,'created_at'=>now(),'updated_at'=>now()],
            ['purchase_order_id'=>$po1,'product_type_id'=>$pt['Serbuk'],
             'target_weight_kg'=>50,'target_box'=>10,'weight_per_box_kg'=>5,'created_at'=>now(),'updated_at'=>now()],
            ['purchase_order_id'=>$po2,'product_type_id'=>$pt['YF STEAK'],
             'target_weight_kg'=>300,'target_box'=>15,'weight_per_box_kg'=>20,'created_at'=>now(),'updated_at'=>now()],
            ['purchase_order_id'=>$po2,'product_type_id'=>$pt['YF LOIN'],
             'target_weight_kg'=>250,'target_box'=>25,'weight_per_box_kg'=>10,'created_at'=>now(),'updated_at'=>now()],
        ]);

        // ── Receivings ─────────────────────────────────────────
        foreach ([
            [$ms['YF LOIN EROPA WARNA'],$po1,800,'Box Polos',20,'NJY',8,false],
            [$ms['YF LOIN EROPA WARNA'],$po1,400,'Keranjang',10,'NJY',4,true],
            [$ms['YF LOIN EROPA SASI'], $po2,600,'Box Polos',15,'OCF',2,false],
        ] as [$subId,$poId,$wt,$ct,$cc,$supCode,$days,$split]) {
            $supplier = \App\Models\Supplier::where('code', $supCode)->firstOrFail();

            $receiving = \App\Models\Receiving::create([
                'material_subgroup_id'=>$subId,'purchase_order_id'=>$poId,
                'supplier_id'=>$supplier->id,
                'weight_kg'=>$wt,'container_type'=>$ct,'container_count'=>$cc,
                'split_per_container'=>$split,
                'received_date'=>Carbon::today()->subDays($days),'created_by'=>$uid,
            ]);

            // Batch Number Induk + label QR terbit di sini. Stok bahan baku
            // ikut dihitung ulang dari saldo batch, bukan ditambah manual.
            app(\App\Services\BatchService::class)->createFromReceiving($receiving);
        }

        // Sesi produksi tidak disemai di sini: rantai Potong -> Trimming ->
        // Steak harus dijalankan berurutan lewat StageService agar jejak
        // batch-nya benar. Data contohnya dibuat TraceabilityDemoSeeder.

        // Packing tidak lagi disemai di sini: box hanya boleh terbit dari lot
        // hasil tahap akhir, sehingga data contohnya dibuat oleh
        // TraceabilityDemoSeeder yang menjalankan rantai produksi penuh.

        // Standar toleransi disemai belakangan karena butuh master bahan & tahap.
        $this->call(YieldStandardSeeder::class);
    }
}
