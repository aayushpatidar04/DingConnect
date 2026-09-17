<?php

namespace App\Exports;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
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
            'State',
            'Pincode',
            'GST Number',
            'PAN Number',
            'Status',
            'KYC Status',
            'Wallet Balance',
            'Total Transactions',
            'Total Spent',
            'Joined Date',
        ];
    }

    public function query(): Builder|QueryBuilder|Relation
    {
        return User::where('role', 'retailer')
            ->with(['wallet', 'transactions'])
            ->orderByDesc('created_at');
    }

    public function map($retailer): array
    {
        return [
            $retailer->id,
            $retailer->name,
            $retailer->email,
            $retailer->phone,
            $retailer->shop_name ?? '-',
            $retailer->address ?? '-',
            $retailer->city ?? '-',
            $retailer->state ?? '-',
            $retailer->pincode ?? '-',
            $retailer->gst_number ?? '-',
            $retailer->pan_number ?? '-',
            'Yes',
            ucfirst($retailer->kyc_status),
            $retailer->wallet->balance ?? 0,
            $retailer->transactions->count(),
            $retailer->transactions->where('status', 'success')->sum('amount'),
            $retailer->created_at->format('Y-m-d H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array|null
    {
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->getStyle('1:1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE5E7EB');

        return null;
    }
}
