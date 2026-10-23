<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CommissionsExport;
use App\Http\Controllers\Controller;
use App\Models\CommissionLevel;
use App\Models\Operator;
use App\Models\RetailerCommission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class CommissionReportController extends Controller
{
    private const FILTER_KEYS = ['search', 'status', 'type', 'retailer_id', 'operator_id', 'level_id', 'from', 'to'];

    public function index(Request $request)
    {
        $filters = $request->only(self::FILTER_KEYS);

        $commissions = RetailerCommission::query()
            ->with([
                'user:id,name,shop_name',
                'operator:id,name',
                'level:id,name',
                'transaction:id,receipt_number,mobile_number',
            ])
            ->filter($filters)
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $row = RetailerCommission::query()
            ->filter($filters)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN status = 'credited' THEN commission_amount END), 0) as total_paid,
                SUM(status = 'credited') as credited,
                SUM(status = 'skipped') as skipped
            ")
            ->first();

        $stats = [
            'total_paid' => (float) $row->total_paid,
            'credited' => (int) $row->credited,
            'skipped' => (int) $row->skipped,
        ];

        return Inertia::render('Admin/Commission/Report', [
            'commissions' => $commissions,
            'stats' => $stats,
            'filters' => $filters,
            'retailers' => User::where('role', 'retailer')->orderBy('name')->get(['id', 'name', 'shop_name']),
            'operators' => Operator::orderBy('name')->get(['id', 'name']),
            'levels' => CommissionLevel::orderBy('min_connections')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request)
    {
        return Excel::download(
            new CommissionsExport($request->only(self::FILTER_KEYS)),
            'commissions_' . now()->format('Y-m-d') . '.xlsx'
        );
    }
}
