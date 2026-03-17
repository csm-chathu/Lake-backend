<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MedicineBrand;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DiagnosticUploadController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,webp',
            'type' => 'nullable|string|max:32',
            'patientId' => 'nullable|integer|exists:patients,id',
        ]);

        $file = $request->file('file');
        $reportType = Str::slug((string) ($data['type'] ?? 'report'));
        if ($reportType === '') {
            $reportType = 'report';
        }

        $directory = 'diagnostic-reports/' . date('Y/m') . '/' . $reportType;
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $filename = Str::uuid()->toString() . '.' . strtolower($extension);

        $storedPath = $file->storeAs($directory, $filename, 'public');
        $publicUrl = url(Storage::url($storedPath));

        return response()->json([
            'fileUrl' => $publicUrl,
            'filePublicId' => $storedPath,
            'mimeType' => $file->getMimeType(),
            'fileBytes' => $file->getSize(),
            'originalName' => $file->getClientOriginalName(),
            'type' => $data['type'] ?? null,
            'reportedAt' => now()->toISOString(),
        ], 201);
    }

    public function storeMedicineBrandImage(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|max:5120|mimes:jpg,jpeg,png,webp',
            'brand_id' => 'nullable|integer|exists:medicine_brands,id',
        ]);

        $file = $request->file('file');
        $directory = 'medicine-brand-images/' . date('Y/m');
        $extension = $file->getClientOriginalExtension() ?: $file->extension() ?: 'bin';
        $filename = Str::uuid()->toString() . '.' . strtolower($extension);

        $storedPath = $file->storeAs($directory, $filename, 'public');
        $publicUrl = url(Storage::url($storedPath));

        if (!empty($data['brand_id'])) {
            $brand = MedicineBrand::find($data['brand_id']);
            if ($brand) {
                $brand->image_url = $publicUrl;
                $brand->save();
            }
        }

        return response()->json([
            'fileUrl' => $publicUrl,
            'filePublicId' => $storedPath,
            'mimeType' => $file->getMimeType(),
            'fileBytes' => $file->getSize(),
            'originalName' => $file->getClientOriginalName(),
            'brandId' => $data['brand_id'] ?? null,
            'brandUpdated' => !empty($data['brand_id']),
        ], 201);
    }
}