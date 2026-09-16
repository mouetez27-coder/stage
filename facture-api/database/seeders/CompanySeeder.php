<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        Company::firstOrCreate(
            ['tax_registration_number' => '1234567R'],
            [
                'name' => 'Ma Société',
                'commercial_register' => 'RC123456',
                'address' => 'Tunis',
                'city' => 'Tunis',
                'postal_code' => '1000',
                'country' => 'TN',
                'phone' => '71222333',
                'email' => 'contact@entreprise.tn',
                'website' => 'https://entreprise.tn',
            ]
        );
    }
}