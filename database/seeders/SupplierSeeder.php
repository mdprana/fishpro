<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['NJY', 'UD Nelayan Jaya',  'Pak Karto',  '0812-3456-7890', 'Muncar, Banyuwangi'],
            ['OCF', 'PT Ocean Fresh',   'Bu Sari',    '0813-2222-1111', 'Benoa, Denpasar'],
            ['SMR', 'PT Samudra Raya',  'Pak Hendra', '0815-7777-8888', 'Pelabuhan Ratu, Sukabumi'],
        ];

        foreach ($suppliers as [$code, $name, $contact, $phone, $city]) {
            Supplier::updateOrCreate(
                ['code' => $code],
                [
                    'name'           => $name,
                    'contact_person' => $contact,
                    'phone'          => $phone,
                    'city'           => $city,
                    'is_active'      => true,
                ]
            );
        }
    }
}
