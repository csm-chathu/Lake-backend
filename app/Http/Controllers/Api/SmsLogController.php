<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SmsLog;
use Illuminate\Http\Request;

class SmsLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = SmsLog::query()
            ->latest()
            ->limit((int) $request->input('limit', 100))
            ->get();

        return response()->json($logs);
    }

    public function count()
    {
        return response()->json(['count' => SmsLog::count()]);
    }
}
