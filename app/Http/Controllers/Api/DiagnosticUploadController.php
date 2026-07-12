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
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'jpg');
        $filename = Str::uuid()->toString() . '.' . $extension;

        // Fallback local upload (used only when frontend direct upload not available)
        $directory = 'medicine-brand-images/' . date('Y/m');
        $storedPath = $file->storeAs($directory, $filename, 'public');
        $publicUrl = url(Storage::url($storedPath));
        $filePublicId = $storedPath;

        if (!empty($data['brand_id']) && $publicUrl) {
            $brand = MedicineBrand::find($data['brand_id']);
            if ($brand) {
                $brand->image_url = $publicUrl;
                $brand->save();
            }
        }

        return response()->json([
            'fileUrl'      => $publicUrl,
            'filePublicId' => $filePublicId,
            'mimeType'     => $file->getMimeType(),
            'fileBytes'    => $file->getSize(),
            'originalName' => $file->getClientOriginalName(),
            'brandId'      => $data['brand_id'] ?? null,
            'brandUpdated' => !empty($data['brand_id']),
        ], 201);
    }

    // Generate ImageKit auth params for direct client-side upload
    public function imagekitAuth()
    {
        $privateKey  = env('IMAGEKIT_PRIVATE_KEY', '');
        $publicKey   = env('IMAGEKIT_PUBLIC_KEY', '');
        $urlEndpoint = env('IMAGEKIT_URL_ENDPOINT', '');

        if (!$privateKey || !$publicKey || !$urlEndpoint) {
            return response()->json(['error' => 'ImageKit not configured'], 503);
        }

        $token  = Str::uuid()->toString();
        $expire = time() + 2400;
        $signature = hash_hmac('sha1', $token . $expire, $privateKey);

        return response()->json([
            'token'       => $token,
            'expire'      => $expire,
            'signature'   => $signature,
            'publicKey'   => $publicKey,
            'urlEndpoint' => $urlEndpoint,
        ]);
    }

    // Save an ImageKit URL onto a brand record
    public function saveBrandImageUrl(Request $request, $brandId)
    {
        $data = $request->validate(['image_url' => 'required|string|max:1000']);
        $brand = MedicineBrand::findOrFail($brandId);
        $brand->image_url = $data['image_url'];
        $brand->save();
        return response()->json(['success' => true, 'image_url' => $brand->image_url]);
    }
}