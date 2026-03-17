<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\DirectSale;
use App\Models\Patient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    private function getHourExpression(): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', date) AS INTEGER)"
            : 'HOUR(date)';
    }

    private function formatHourLabel(int $hour): string
    {
        $suffix = $hour >= 12 ? 'PM' : 'AM';
        $display = $hour % 12;
        if ($display === 0) {
            $display = 12;
        }
        return $display . $suffix;
    }

    private function bestTwoHourWindows(array $hourlyTotals): array
    {
        $candidates = [];
        for ($hour = 0; $hour <= 22; $hour++) {
            $value = ($hourlyTotals[$hour] ?? 0) + ($hourlyTotals[$hour + 1] ?? 0);
            $candidates[] = [
                'startHour' => $hour,
                'endHour' => $hour + 2,
                'total' => round((float) $value, 2),
            ];
        }

        usort($candidates, fn ($a, $b) => $b['total'] <=> $a['total']);

        $picked = [];
        foreach ($candidates as $candidate) {
            if ($candidate['total'] <= 0) {
                continue;
            }

            $overlaps = collect($picked)->contains(function ($existing) use ($candidate) {
                return !(
                    $candidate['endHour'] <= $existing['startHour']
                    || $candidate['startHour'] >= $existing['endHour']
                );
            });

            if ($overlaps) {
                continue;
            }

            $picked[] = [
                'label' => $this->formatHourLabel($candidate['startHour']) . ' - ' . $this->formatHourLabel($candidate['endHour']),
                'startHour' => $candidate['startHour'],
                'endHour' => $candidate['endHour'],
                'total' => $candidate['total'],
            ];

            if (count($picked) >= 2) {
                break;
            }
        }

        return $picked;
    }

    private function topProductsBetween(string $startAt, string $endAt, int $limit = 5)
    {
        return DB::table('direct_sale_items as dsi')
            ->join('direct_sales as ds', 'dsi.direct_sale_id', '=', 'ds.id')
            ->leftJoin('medicine_brands as mb', 'dsi.medicine_brand_id', '=', 'mb.id')
            ->leftJoin('medicines as m', 'mb.medicine_id', '=', 'm.id')
            ->whereBetween('ds.date', [$startAt, $endAt])
            ->groupBy('dsi.medicine_brand_id', 'mb.name', 'm.name')
            ->selectRaw('dsi.medicine_brand_id as medicine_brand_id')
            ->selectRaw('mb.name as brand_name')
            ->selectRaw('m.name as medicine_name')
            ->selectRaw('COALESCE(SUM(dsi.quantity), 0) as sold_qty')
            ->selectRaw('COALESCE(SUM(dsi.line_total), 0) as sold_amount')
            ->orderByDesc('sold_qty')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $name = trim(implode(' - ', array_filter([
                    $row->medicine_name,
                    $row->brand_name,
                ])));

                return [
                    'medicineBrandId' => $row->medicine_brand_id,
                    'name' => $name !== '' ? $name : 'Unknown item',
                    'soldQty' => round((float) ($row->sold_qty ?? 0), 2),
                    'soldAmount' => round((float) ($row->sold_amount ?? 0), 2),
                ];
            })
            ->values();
    }

    private function slowMovingItemsForMonth(string $monthStart, string $monthEnd, int $limit = 10)
    {
        return DB::table('medicine_brands as mb')
            ->leftJoin('medicines as m', 'mb.medicine_id', '=', 'm.id')
            ->leftJoin('direct_sale_items as dsi', 'mb.id', '=', 'dsi.medicine_brand_id')
            ->leftJoin('direct_sales as ds', function ($join) use ($monthStart, $monthEnd) {
                $join->on('dsi.direct_sale_id', '=', 'ds.id')
                    ->whereBetween('ds.date', [$monthStart, $monthEnd]);
            })
            ->groupBy('mb.id', 'mb.name', 'm.name', 'mb.stock')
            ->selectRaw('mb.id as medicine_brand_id')
            ->selectRaw('mb.name as brand_name')
            ->selectRaw('m.name as medicine_name')
            ->selectRaw('COALESCE(mb.stock, 0) as stock')
            ->selectRaw('COALESCE(SUM(CASE WHEN ds.id IS NOT NULL THEN dsi.quantity ELSE 0 END), 0) as sold_qty')
            ->orderBy('sold_qty')
            ->orderByDesc('stock')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $name = trim(implode(' - ', array_filter([
                    $row->medicine_name,
                    $row->brand_name,
                ])));

                return [
                    'medicineBrandId' => $row->medicine_brand_id,
                    'name' => $name !== '' ? $name : 'Unknown item',
                    'soldQty' => round((float) ($row->sold_qty ?? 0), 2),
                    'stock' => round((float) ($row->stock ?? 0), 2),
                ];
            })
            ->values();
    }

    public function getSalesHeatmapAnalytics(Request $request)
    {
        $data = $request->validate([
            'startDate' => 'required|date_format:Y-m-d',
            'endDate' => 'required|date_format:Y-m-d|after_or_equal:startDate'
        ]);

        $startDate = $data['startDate'] . ' 00:00:00';
        $endDate = $data['endDate'] . ' 23:59:59';

        $hourExpression = $this->getHourExpression();
        $hourlyRows = DirectSale::whereBetween('date', [$startDate, $endDate])
            ->selectRaw("{$hourExpression} as sales_hour")
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->groupBy('sales_hour')
            ->orderBy('sales_hour')
            ->get();

        $hourlyTotals = array_fill(0, 24, 0.0);
        foreach ($hourlyRows as $row) {
            $hour = (int) ($row->sales_hour ?? 0);
            if ($hour >= 0 && $hour <= 23) {
                $hourlyTotals[$hour] = (float) ($row->total ?? 0);
            }
        }

        $salesHeatmap = collect(range(0, 23))->map(function ($hour) use ($hourlyTotals) {
            return [
                'hour' => $hour,
                'label' => $this->formatHourLabel($hour),
                'total' => round((float) ($hourlyTotals[$hour] ?? 0), 2),
            ];
        })->values();

        $bestSalesTime = $this->bestTwoHourWindows($hourlyTotals);

        $todayStart = now()->startOfDay()->format('Y-m-d H:i:s');
        $todayEnd = now()->endOfDay()->format('Y-m-d H:i:s');
        $monthStart = now()->startOfMonth()->format('Y-m-d H:i:s');
        $monthEnd = now()->endOfMonth()->format('Y-m-d H:i:s');

        $topProductsToday = $this->topProductsBetween($todayStart, $todayEnd, 5);
        $topProductsThisMonth = $this->topProductsBetween($monthStart, $monthEnd, 8);
        $slowMovingItems = $this->slowMovingItemsForMonth($monthStart, $monthEnd, 10);

        return response()->json([
            'salesHeatmap' => $salesHeatmap,
            'bestSalesTime' => $bestSalesTime,
            'topProductsToday' => $topProductsToday,
            'topProductsThisMonth' => $topProductsThisMonth,
            'slowMovingItems' => $slowMovingItems,
            'period' => [
                'startDate' => $data['startDate'],
                'endDate' => $data['endDate']
            ]
        ]);
    }

    /**
     * Get comprehensive report data for a date range
     * Query params: startDate (ISO date), endDate (ISO date)
     */
    public function getComprehensiveReport(Request $request)
    {
        $data = $request->validate([
            'startDate' => 'required|date_format:Y-m-d',
            'endDate' => 'required|date_format:Y-m-d|after_or_equal:startDate'
        ]);

        $startDate = $data['startDate'] . ' 00:00:00';
        $endDate = $data['endDate'] . ' 23:59:59';

        // Get revenue data
        $appointmentRevenue = Appointment::whereBetween('date', [$startDate, $endDate])
            ->select(DB::raw('COALESCE(SUM(COALESCE(total_charge, 0)), 0) as total'))
            ->value('total') ?? 0;

        $salesRevenue = DirectSale::whereBetween('date', [$startDate, $endDate])
            ->select(DB::raw('COALESCE(SUM(total), 0) as total'))
            ->value('total') ?? 0;

        // Total appointments in period
        $totalAppointments = Appointment::whereBetween('date', [$startDate, $endDate])->count();

        // Total direct sales
        $totalSales = DirectSale::whereBetween('date', [$startDate, $endDate])->count();

        // Unique patients visited
        $totalPatients = Appointment::whereBetween('date', [$startDate, $endDate])
            ->distinct('patient_id')
            ->count();

        // Gender distribution of patients in appointments
        $genderData = Appointment::whereBetween('appointments.date', [$startDate, $endDate])
            ->join('patients', 'appointments.patient_id', '=', 'patients.id')
            ->select('patients.gender', DB::raw('COUNT(*) as count'))
            ->whereNotNull('patients.gender')
            ->groupBy('patients.gender')
            ->get();

        $maleCount = 0;
        $femaleCount = 0;
        
        foreach ($genderData as $row) {
            if (strtolower($row->gender) === 'male') {
                $maleCount = (int) $row->count;
            } elseif (strtolower($row->gender) === 'female') {
                $femaleCount = (int) $row->count;
            }
        }

        // Top 5 most common reasons
        $commonReasons = Appointment::whereBetween('date', [$startDate, $endDate])
            ->select('reason', DB::raw('COUNT(*) as count'))
            ->groupBy('reason')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'reason' => $item->reason,
                    'count' => $item->count
                ];
            })
            ->toArray();

        // Most visited patients (top 10)
        $mostVisitedPatients = Appointment::whereBetween('appointments.date', [$startDate, $endDate])
            ->join('patients', 'appointments.patient_id', '=', 'patients.id')
            ->leftJoin('owners', 'patients.owner_id', '=', 'owners.id')
            ->select(
                'patients.id',
                'patients.name',
                DB::raw("COALESCE(owners.first_name, '') || ' ' || COALESCE(owners.last_name, '') as ownerName"),
                DB::raw('COUNT(appointments.id) as visitCount'),
                DB::raw('COALESCE(SUM(appointments.total_charge), 0) as totalRevenue')
            )
            ->groupBy('patients.id', 'patients.name')
            ->orderByDesc('visitCount')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'ownerName' => trim($item->ownerName),
                    'visitCount' => $item->visitCount,
                    'totalRevenue' => (float) $item->totalRevenue
                ];
            })
            ->toArray();

        $hourExpression = $this->getHourExpression();
        $hourlyRows = DirectSale::whereBetween('date', [$startDate, $endDate])
            ->selectRaw("{$hourExpression} as sales_hour")
            ->selectRaw('COALESCE(SUM(total), 0) as total')
            ->selectRaw('COUNT(*) as sales_count')
            ->groupBy('sales_hour')
            ->orderBy('sales_hour')
            ->get();

        $hourlyTotals = array_fill(0, 24, 0.0);
        foreach ($hourlyRows as $row) {
            $hour = (int) ($row->sales_hour ?? 0);
            if ($hour >= 0 && $hour <= 23) {
                $hourlyTotals[$hour] = (float) ($row->total ?? 0);
            }
        }

        $salesHeatmap = collect(range(0, 23))->map(function ($hour) use ($hourlyTotals) {
            return [
                'hour' => $hour,
                'label' => $this->formatHourLabel($hour),
                'total' => round((float) ($hourlyTotals[$hour] ?? 0), 2),
            ];
        })->values();

        $bestSalesTime = $this->bestTwoHourWindows($hourlyTotals);

        $todayStart = now()->startOfDay()->format('Y-m-d H:i:s');
        $todayEnd = now()->endOfDay()->format('Y-m-d H:i:s');
        $monthStart = now()->startOfMonth()->format('Y-m-d H:i:s');
        $monthEnd = now()->endOfMonth()->format('Y-m-d H:i:s');

        $topProductsToday = $this->topProductsBetween($todayStart, $todayEnd, 5);
        $topProductsThisMonth = $this->topProductsBetween($monthStart, $monthEnd, 8);
        $slowMovingItems = $this->slowMovingItemsForMonth($monthStart, $monthEnd, 10);

        return response()->json([
            'appointmentRevenue' => round((float)$appointmentRevenue, 2),
            'salesRevenue' => round((float)$salesRevenue, 2),
            'totalRevenue' => round((float)$appointmentRevenue + (float)$salesRevenue, 2),
            'totalAppointments' => $totalAppointments,
            'totalSales' => $totalSales,
            'totalPatients' => $totalPatients,
            'maleCount' => $maleCount,
            'femaleCount' => $femaleCount,
            'commonReasons' => $commonReasons,
            'mostVisitedPatients' => $mostVisitedPatients,
            'salesHeatmap' => $salesHeatmap,
            'bestSalesTime' => $bestSalesTime,
            'topProductsToday' => $topProductsToday,
            'topProductsThisMonth' => $topProductsThisMonth,
            'slowMovingItems' => $slowMovingItems,
            'period' => [
                'startDate' => $data['startDate'],
                'endDate' => $data['endDate']
            ]
        ]);
    }
}
