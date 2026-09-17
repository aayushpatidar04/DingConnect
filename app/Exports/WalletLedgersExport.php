<?php

namespace App\Exports;

use App\Models\WalletLedger;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class WalletLedgersExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(protected int $userId)
    {
    }

    public function headings(): array
    {
        return [
            'ID',
            'Date',
            'Type',
            'Amount',
            'Balance Before',
            'Balance After',
            'Reference Type',
            'Reference ID',
            'Description',
        ];
    }

    public function query(): \Illuminate\Database\Eloquent\Builder
    {
        return WalletLedger::query()
            ->whereHas('wallet', fn($q) => $q->where('user_id', $this->userId))
            ->orderByDesc('created_at');
    }

    public function map($ledger): array
    {
        return [
            $ledger->id,
            $ledger->created_at->format('Y-m-d H:i:s'),
            ucfirst($ledger->type),
            $ledger->amount,
            $ledger->balance_before,
            $ledger->balance_after,
            $ledger->reference_type ?? '-',
            $ledger->reference_id ?? '-',
            $ledger->description ?? '-',
        ];
    }

    public function styles(Worksheet $sheet): array|null
    {
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->getStyle('1:1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE5E7EB');

        return [];
    }
}
