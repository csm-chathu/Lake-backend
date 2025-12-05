<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Owner;

class OwnerController extends Controller
{
    private function formatOwner($owner)
    {
        return [
            'id' => $owner->id,
            'firstName' => $owner->first_name,
            'lastName' => $owner->last_name,
            'email' => $owner->email,
            'phone' => $owner->phone,
            'createdAt' => $owner->created_at,
            'updatedAt' => $owner->updated_at
        ];
    }

    public function index()
    {
        $owners = Owner::orderBy('created_at', 'desc')->get();
        return response()->json($owners->map(fn($o) => $this->formatOwner($o)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'firstName' => 'nullable|string|max:120',
            'lastName' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:50'
        ]);

        $owner = Owner::create([
            'first_name' => $data['firstName'] ?? null,
            'last_name' => $data['lastName'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null
        ]);

        return response()->json($this->formatOwner($owner), 201);
    }

    public function show($id)
    {
        $owner = Owner::find($id);
        if (! $owner) {
            return response()->json(['message' => 'Owner not found'], 404);
        }
        return response()->json($this->formatOwner($owner));
    }

    public function update(Request $request, $id)
    {
        $owner = Owner::find($id);
        if (! $owner) {
            return response()->json(['message' => 'Owner not found'], 404);
        }

        $data = $request->validate([
            'firstName' => 'nullable|string|max:120',
            'lastName' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:200',
            'phone' => 'nullable|string|max:50'
        ]);

        $updateData = [];
        if (isset($data['firstName'])) $updateData['first_name'] = $data['firstName'];
        if (isset($data['lastName'])) $updateData['last_name'] = $data['lastName'];
        if (isset($data['email'])) $updateData['email'] = $data['email'];
        if (isset($data['phone'])) $updateData['phone'] = $data['phone'];

        $owner->update($updateData);
        return response()->json($this->formatOwner($owner));
    }

    public function destroy($id)
    {
        $owner = Owner::find($id);
        if (! $owner) {
            return response()->json(['message' => 'Owner not found'], 404);
        }
        $owner->delete();
        return response()->noContent();
    }
}
