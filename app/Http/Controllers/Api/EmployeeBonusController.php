<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeBonus;
use Illuminate\Http\Request;

class EmployeeBonusController extends Controller
{
    public function index()
    {
        return response()->json(
            EmployeeBonus::with('employee')
                ->orderByDesc('bonus_date')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'bonus_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $bonus = EmployeeBonus::create($validated);

        return response()->json($bonus->load('employee'), 201);
    }

    public function show($id)
    {
        return response()->json(EmployeeBonus::with('employee')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $bonus = EmployeeBonus::findOrFail($id);

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'bonus_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $bonus->update($validated);

        return response()->json($bonus->fresh()->load('employee'));
    }

    public function destroy($id)
    {
        $bonus = EmployeeBonus::findOrFail($id);
        $bonus->delete();

        return response()->json(null, 204);
    }
}
