<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClinicSetting;
use Illuminate\Http\Request;

class ClinicSettingController extends Controller
{
    public function show()
    {
        $settings = ClinicSetting::first();

        if (! $settings) {
            $settings = ClinicSetting::create([
                'name' => 'THE LAKE ANIMAL CLINIC',
                'phone' => '071 730 7641',
                'email' => null,
                'address' => 'Wewa Rd, Anuradhapura',
                'description' => 'Your trusted partner in pet healthcare. Manage appointments, medical records, and care for your beloved pets with confidence.',
                'pos_description' => 'POS System for billing, sales, and day-end operations.',
                'timezone' => config('app.timezone'),
                'currency_code' => 'LKR',
                'logo_url' => null,
                'hero_image_url' => 'vet-clinic-hero.jpg',
                'sms_sender_id' => config('sms.sender_id'),
                'shop_type' => 'vet',
                'service_charge_percentage' => 0.00,
            ]);
        }

        return response()->json($settings);
    }

    public function update(Request $request)
    {
        $settings = ClinicSetting::firstOrCreate([], [
            'name' => 'Clinic',
            'currency_code' => 'LKR',
            'timezone' => config('app.timezone')
        ]);

        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:64',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:1024',
            'description' => 'nullable|string|max:5000',
            'pos_description' => 'nullable|string|max:5000',
            'timezone' => 'nullable|string|max:64',
            'currency_code' => 'nullable|string|max:8',
            'logo_url' => 'nullable|url|max:2048',
            'hero_image_url' => 'nullable|string|max:2048',
            'sms_sender_id' => 'nullable|string|max:64',
            'shop_type' => 'nullable|string|max:64',
            'service_charge_percentage' => 'nullable|numeric|min:0|max:100'
        ]);

        $settings->update($data);

        return response()->json($settings->fresh());
    }
}
