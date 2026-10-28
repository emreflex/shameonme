<?php

namespace Database\Seeders;

use App\Models\ApplicationSetting;
use App\Models\Department;
use App\Models\Role;
use App\Models\ShiftType;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['petrol' => 'Petrol İstasyonu', 'market' => 'Market'] as $code => $name) {
            Department::firstOrCreate(['code' => $code], ['name' => $name]);
        }
        foreach (['super_admin', 'manager', 'staff'] as $name) {
            Role::firstOrCreate(['name' => $name]);
        }
        foreach (['Sabah', 'Akşam', 'Gece', 'Ara Vardiya'] as $name) {
            ShiftType::firstOrCreate(['name' => $name]);
        }
        ApplicationSetting::firstOrCreate(['key' => 'business_name'], ['value' => 'İstasyon & Market']);
    }
}
