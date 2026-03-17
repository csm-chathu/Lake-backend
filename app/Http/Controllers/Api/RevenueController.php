<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\DirectSale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevenueController extends Controller
{
    /**
     * Get aggregated revenue for a date range
     * Query params: startDate (ISO date), endDate (ISO date)
     */
    public function getRevenue(Request $request)
    {
        $data = $request->validate([
            'startDate' => 'required|date_format:Y-m-d',
            'endDate' => 'required|date_format:Y-m-d|after_or_equal:startDate'
        ]);

        $startDate = $data['startDate'] . ' 00:00:00';
        $endDate = $data['endDate'] . ' 23:59:59';

        // Get aggregated appointment revenue (total charges - discount)
        $appointmentRevenue = Appointment::whereBetween('date', [$startDate, $endDate])
            ->select(DB::raw('COALESCE(SUM(COALESCE(total_charge, 0)), 0) as total'))
            ->value('total') ?? 0;

        // Get aggregated direct sales revenue
        $salesRevenue = DirectSale::whereBetween('date', [$startDate, $endDate])
            ->select(DB::raw('COALESCE(SUM(total), 0) as total'))
            ->value('total') ?? 0;

        $appointmentRevenue = (float) $appointmentRevenue;
        $salesRevenue = (float) $salesRevenue;
        $totalRevenue = $appointmentRevenue + $salesRevenue;

        return response()->json([
            'appointmentRevenue' => round($appointmentRevenue, 2),
            'salesRevenue' => round($salesRevenue, 2),
            'totalRevenue' => round($totalRevenue, 2),
            'period' => [
                'startDate' => $data['startDate'],
                'endDate' => $data['endDate']
            ]
        ]);
    }
}
