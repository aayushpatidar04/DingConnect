<?php

namespace App\Exports;

use App\Models\RetailerCommission;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CommissionsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected array $filters = []
    ) {
    }

    public function headings(): array
    {
        return [
            'Date',
            'Retailer',
            'Shop',
            'Receipt Number',
            'Mobile / Serial',
            'Operator',
            'Level',
            'Type',
            'Top-up #',
            'Recharge Amount',
            'Top Up',
            'Bonus',
            'Commission',
            'Status',
            'Skip Reason',
        ];
    }

    public function query(): Builder
    {
        return RetailerCommission::query()
            ->with(['user:id,name,shop_name', 'operator:id,name', 'level:id,name', 'transaction:id,receipt_number,mobile_number'])
            ->filter($this->filters)
            ->orderByDesc('created_at');
    }

    public function map($commission): array
    {
        return [
            $commission->created_at?->format('Y-m-d H:i:s') ?? '-',
            $commission->user?->name ?? '-',
            $commission->user?->shop_name ?? '-',
            $commission->transaction?->receipt_number ?? '-',
            $commission->transaction?->mobile_number ?? '-',
            $commission->operator?->name ?? '-',
            $commission->level?->name ?? '-',
            $commission->type ? ucfirst($commission->type) : '-',
            $commission->topup_sequence ?? '-',
            (float) $commission->recharge_amount,
            (float) $commission->topup_amount,
            (float) $commission->bonus_amount,
            (float) $commission->commission_amount,
            ucfirst($commission->status),
            $commission->skip_reason ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->getStyle('1:1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE5E7EB');

        return [];
    }
}
