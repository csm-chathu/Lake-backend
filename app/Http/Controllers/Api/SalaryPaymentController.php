<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalaryPayment;
use Illuminate\Http\Request;

class SalaryPaymentController extends Controller
{
    private function computeNet(array $data): float
    {
        $base = (float) ($data['base_salary'] ?? 0);
        $bonus = (float) ($data['bonus_amount'] ?? 0);
        $deductions = (float) ($data['deductions'] ?? 0);

        return max(0, round($base + $bonus - $deductions, 2));
    }

    public function index()
    {
        return response()->json(
            SalaryPayment::with('employee')
                ->orderByDesc('salary_month')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'salary_month' => 'required|date',
            'base_salary' => 'nullable|numeric|min:0',
            'bonus_amount' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'payment_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $payload = [
            ...$validated,
            'base_salary' => $validated['base_salary'] ?? 0,
            'bonus_amount' => $validated['bonus_amount'] ?? 0,
            'deductions' => $validated['deductions'] ?? 0,
        ];
        $payload['net_salary'] = $this->computeNet($payload);

        $salary = SalaryPayment::create($payload);

        return response()->json($salary->load('employee'), 201);
    }

    public function show($id)
    {
        return response()->json(SalaryPayment::with('employee')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $salary = SalaryPayment::findOrFail($id);

        $validated = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'salary_month' => 'required|date',
            'base_salary' => 'nullable|numeric|min:0',
            'bonus_amount' => 'nullable|numeric|min:0',
            'deductions' => 'nullable|numeric|min:0',
            'payment_date' => 'nullable|date',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        $payload = [
            ...$validated,
            'base_salary' => $validated['base_salary'] ?? 0,
            'bonus_amount' => $validated['bonus_amount'] ?? 0,
            'deductions' => $validated['deductions'] ?? 0,
        ];
        $payload['net_salary'] = $this->computeNet($payload);

        $salary->update($payload);

        return response()->json($salary->fresh()->load('employee'));
    }

    public function destroy($id)
    {
        $salary = SalaryPayment::findOrFail($id);
        $salary->delete();

        return response()->json(null, 204);
    }
}
