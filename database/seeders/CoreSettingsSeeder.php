<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class CoreSettingsSeeder extends Seeder
{
    public function run()
    {
        $settings = [
            ['name' => 'Title English', 'key' => 'title_english', 'value' => '', 'description' => 'Hospital Title in English'],
            ['name' => 'Title Hindi', 'key' => 'title_hindi', 'value' => '', 'description' => 'Hospital Title in Hindi'],
            ['name' => 'Phone Number 1', 'key' => 'phone_1', 'value' => '', 'description' => 'Primary Phone Number'],
            ['name' => 'Phone Number 2', 'key' => 'phone_2', 'value' => '', 'description' => 'Secondary Phone Number'],
            ['name' => 'Phone Number 3', 'key' => 'phone_3', 'value' => '', 'description' => 'Tertiary Phone Number'],
            ['name' => 'Address English', 'key' => 'address_english', 'value' => '', 'description' => 'Address in English'],
            ['name' => 'Address Hindi', 'key' => 'address_hindi', 'value' => '', 'description' => 'Address in Hindi'],
            ['name' => 'Timings English', 'key' => 'timings_english', 'value' => '', 'description' => 'Timings in English'],
            ['name' => 'Timings Hindi', 'key' => 'timings_hindi', 'value' => '', 'description' => 'Timings in Hindi'],
            ['name' => 'Registration Number', 'key' => 'registration_number', 'value' => '', 'description' => 'Hospital Registration Number'],
            ['name' => 'Addition Information English', 'key' => 'info_english', 'value' => '', 'description' => 'Additional Information in English'],
            ['name' => 'Addition Information Hindi', 'key' => 'info_hindi', 'value' => '', 'description' => 'Additional Information in Hindi'],
            ['name' => 'Banner Image 1', 'key' => 'banner_1', 'value' => '', 'description' => 'Banner Image 1'],
            ['name' => 'Banner Image 2', 'key' => 'banner_2', 'value' => '', 'description' => 'Banner Image 2'],
            ['name' => 'Banner Image 3', 'key' => 'banner_3', 'value' => '', 'description' => 'Banner Image 3'],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(['key' => $setting['key']], [
                'name' => $setting['name'],
                'description' => $setting['description'],
                'field' => json_encode(['name' => 'value', 'type' => 'text', 'title' => 'Value']),
                'active' => 1,
            ]);
        }
    }
}
