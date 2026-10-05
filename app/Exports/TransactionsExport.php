<?php

namespace App\Exports;

use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransactionsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected ?string $from = null,
        protected ?string $to = null,
        protected ?string $status = null,
    ) {
    }

    public function headings(): array
    {
        return [
            'Transaction ID',
            'Receipt Number',
            'Date',
            'Retailer',
            'Shop',
            'Mobile Number',
            'Operator',
            'Country',
            'Amount',
            'Currency',
            'Ding Transaction ID',
            'Status',
            'Failure Reason',
        ];
    }

    public function query(): Builder
    {
        return Transaction::query()
            ->with(['user:id,name,shop_name', 'operator:id,name', 'country:id,name'])
            ->when($this->from, fn ($q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->to, fn ($q) => $q->whereDate('created_at', '<=', $this->to))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->orderByDesc('created_at');
    }

    public function map($transaction): array
    {
        return [
            $transaction->id,
            $transaction->receipt_number ?? '-',
            $transaction->created_at?->format('Y-m-d H:i:s') ?? '-',
            $transaction->user?->name ?? '-',
            $transaction->user?->shop_name ?? '-',
            $transaction->mobile_number,
            $transaction->operator?->name ?? '-',
            $transaction->country?->name ?? '-',
            (float) $transaction->amount,
            $transaction->currency ?? '-',
            $transaction->ding_transaction_id ?? '-',
            ucfirst($transaction->status),
            $transaction->failure_reason ?? '-',
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