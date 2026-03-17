<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index()
    {
        return response()->json(Employee::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'basic_salary' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive',
            'join_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $employee = Employee::create([
            ...$validated,
            'basic_salary' => $validated['basic_salary'] ?? 0,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json($employee, 201);
    }

    public function show($id)
    {
        return response()->json(Employee::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'role' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'basic_salary' => 'nullable|numeric|min:0',
            'status' => 'nullable|in:active,inactive',
            'join_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $employee->update([
            ...$validated,
            'basic_salary' => $validated['basic_salary'] ?? 0,
            'status' => $validated['status'] ?? 'active',
        ]);

        return response()->json($employee);
    }

    public function destroy($id)
    {
        $employee = Employee::findOrFail($id);
        $employee->delete();

        return response()->json(null, 204);
    }
}
