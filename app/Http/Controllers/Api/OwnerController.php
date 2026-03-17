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
            'patientsCount' => $owner->patients_count ?? ($owner->patients ? $owner->patients->count() : 0),
            'createdAt' => $owner->created_at,
            'updatedAt' => $owner->updated_at
        ];
    }

    public function index()
    {
        $owners = Owner::withCount('patients')->orderBy('created_at', 'desc')->get();
        return response()->json($owners->map(fn($o) => $this->formatOwner($o)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'firstName' => 'required|string|max:120',
            'lastName' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:200',
            'phone' => [
                'required',
                'regex:/^(?:0|\+94)(?:7\d{8}|11\d{7}|[1-9]\d{8})$/',
            ]
        ], [
            'phone.regex' => 'Enter a valid Sri Lankan phone number (e.g. 0771234567 or +94771234567).'
        ]);

        $owner = Owner::create([
            'first_name' => $data['firstName'] ?? null,
            'last_name' => $data['lastName'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null
        ]);
        $owner->loadCount('patients');

        return response()->json($this->formatOwner($owner), 201);
    }

    public function show($id)
    {
        $owner = Owner::find($id);
        if (! $owner) {
            return response()->json(['message' => 'Owner not found'], 404);
        }
        $owner->loadCount('patients');
        return response()->json($this->formatOwner($owner));
    }

    public function update(Request $request, $id)
    {
        $owner = Owner::find($id);
        if (! $owner) {
            return response()->json(['message' => 'Owner not found'], 404);
        }

        $data = $request->validate([
            'firstName' => 'sometimes|required|string|max:120',
            'lastName' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:200',
            'phone' => [
                'sometimes','required',
                'regex:/^(?:0|\+94)(?:7\d{8}|11\d{7}|[1-9]\d{8})$/'
            ]
        ], [
            'phone.regex' => 'Enter a valid Sri Lankan phone number (e.g. 0771234567 or +94771234567).'
        ]);

        $updateData = [];
        if (isset($data['firstName'])) $updateData['first_name'] = $data['firstName'];
        if (isset($data['lastName'])) $updateData['last_name'] = $data['lastName'];
        if (isset($data['email'])) $updateData['email'] = $data['email'];
        if (isset($data['phone'])) $updateData['phone'] = $data['phone'];

        $owner->update($updateData);
        $owner->loadCount('patients');
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
