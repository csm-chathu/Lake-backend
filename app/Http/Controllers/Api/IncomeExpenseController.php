<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IncomeExpense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IncomeExpenseController extends Controller
{
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'type' => 'required|in:income,expense',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
        ]);

        $incomeExpense = IncomeExpense::findOrFail($id);
        $incomeExpense->update([
            'type' => $request->type,
            'description' => $request->description,
            'amount' => $request->amount,
        ]);

        return response()->json($incomeExpense->load('user:id,name'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $incomeExpense = IncomeExpense::findOrFail($id);
        $incomeExpense->delete();
        return response()->json(['message' => 'Deleted']);
    }
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            if ($user && (
                $user->user_type === 'cashier' ||
                $user->user_type === 'pos_admin' ||
                $user->user_type === 'admin' ||
                $user->user_type === 'doctor'
            )) {
                return $next($request);
            }
            return response()->json(['message' => 'Unauthorized'], 403);
        });
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return IncomeExpense::with('user:id,name')->latest()->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:income,expense',
            'description' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
        ]);

        $incomeExpense = IncomeExpense::create([
            'type' => $request->type,
            'description' => $request->description,
            'amount' => $request->amount,
            'user_id' => Auth::id(),
        ]);

        return response()->json($incomeExpense->load('user:id,name'), 201);
    }
}
