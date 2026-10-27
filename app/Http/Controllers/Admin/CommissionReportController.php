<?php

namespace App\Http\Controllers\Admin;

use App\Exports\CommissionsExport;
use App\Http\Controllers\Controller;
use App\Models\CommissionLevel;
use App\Models\Operator;
use App\Models\RetailerCommission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class CommissionReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->validatedFilters($request);

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

        $data = [
            'commissions' => $commissions,
            'stats' => $stats,
            'filters' => $filters,
            'retailers' => User::where('role', 'retailer')->orderBy('name')->get(['id', 'name', 'shop_name']),
            'operators' => Operator::orderBy('name')->get(['id', 'name']),
            'levels' => CommissionLevel::orderBy('min_connections')->get(['id', 'name']),
        ];

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'data' => $data]);
        }

        return Inertia::render('Admin/Commission/Report', $data);
    }

    public function export(Request $request)
    {
        return Excel::download(
            new CommissionsExport($this->validatedFilters($request)),
            'commissions_'.now()->format('Y-m-d').'.xlsx'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['credited', 'skipped'])],
            'type' => ['nullable', Rule::in(['first', 'ongoing'])],
            'retailer_id' => ['nullable', 'integer', 'exists:users,id'],
            'operator_id' => ['nullable', 'integer', 'exists:operators,id'],
            'level_id' => ['nullable', 'integer', 'exists:commission_levels,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);
    }
}
