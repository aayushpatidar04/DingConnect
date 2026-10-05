<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RetailersExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Email',
            'Phone',
            'Shop Name',
            'Address',
            'City',
            'County',
            'Postcode',
            'VAT Number',
            'Company Reg Number',
            'UTR Number',
            'Status',
            'KYC Status',
            'KYC Verified At',
            'KYC Rejection Reason',
            'Wallet Balance',
            'Total Transactions',
            'Total Spent',
            'Last Login',
            'Joined Date',
        ];
    }

    public function query(): Builder|QueryBuilder|Relation
    {
        return User::role('retailer') // Spatie scope; see note below
            ->with('wallet')
            ->withCount('transactions')
            ->withSum(
                ['transactions as total_spent' => fn ($q) => $q->where('status', 'success')],
                'amount'
            )
            ->orderByDesc('created_at');
    }

    public function map($retailer): array
    {
        return [
            $retailer->id,
            $retailer->name,
            $retailer->email,
            $retailer->phone ?? '-',
            $retailer->shop_name ?? '-',
            $retailer->address ?? '-',
            $retailer->city ?? '-',
            $retailer->county ?? '-',
            $retailer->postcode ?? '-',
            $retailer->vat_number ?? '-',
            $retailer->company_reg_number ?? '-',
            $retailer->utr_number ?? '-',
            $retailer->is_active ? 'Active' : 'Inactive',
            $retailer->kyc_status ? ucfirst($retailer->kyc_status) : '-',
            $retailer->kyc_verified_at?->format('Y-m-d H:i') ?? '-',
            $retailer->kyc_rejection_reason ?? '-',
            (float) ($retailer->wallet?->balance ?? 0),
            $retailer->transactions_count,
            (float) ($retailer->total_spent ?? 0),
            $retailer->last_login_at?->format('Y-m-d H:i') ?? '-',
            $retailer->created_at->format('Y-m-d H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array|null
    {
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->getStyle('1:1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE5E7EB');

        return null;
    }
}