<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OrderReportExport implements FromCollection, WithHeadings, WithMapping, WithEvents
{
    protected $start;
    protected $end;
    protected $orders;
    protected $summary;

    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end   = $end;

        $this->orders = Order::with(['customer', 'orderItems.product.category'])
            ->when($start, fn($q) => $q->whereDate('order_date', '>=', $start))
            ->when($end, fn($q) => $q->whereDate('order_date', '<=', $end))
            ->get();

        $this->summary = $this->buildSummary();
    }

    private function buildSummary()
    {
        $totalOrders = $this->orders->count();

        $totalRevenue = $this->orders->sum(
            fn($o) => $o->orderItems->sum(
                fn($i) => $i->quantity * $i->unit_price
            )
        );

        $avgOrderValue = $totalOrders ? $totalRevenue / $totalOrders : 0;

        $productCount = [];
        foreach ($this->orders as $order) {
            foreach ($order->orderItems as $item) {
                $productCount[$item->product->name] =
                    ($productCount[$item->product->name] ?? 0) + $item->quantity;
            }
        }

        arsort($productCount);
        $top3 = implode(', ', array_slice(array_keys($productCount), 0, 3));

        return [
            'totalOrders' => $totalOrders,
            'totalRevenue' => $totalRevenue,
            'avgOrderValue' => $avgOrderValue,
            'topProducts' => $top3,
        ];
    }

    public function collection()
    {
        $rows = new Collection();

        foreach ($this->orders as $order) {
            foreach ($order->orderItems as $item) {
                $rows->push([$order, $item]);
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return [
            'Order Date',
            'Customer',
            'State',
            'Category',
            'Product',
            'Qty',
            'Unit Price (RM)',
            'Subtotal (RM)'
        ];
    }

    public function map($row): array
    {
        [$order, $item] = $row;

        return [
            $order->order_date->format('Y-m-d'),
            $order->customer->name,
            $order->customer->state ?? '-',
            $item->product->category->name ?? '-',
            $item->product->name,
            $item->quantity,
            number_format($item->unit_price, 2),
            number_format($item->quantity * $item->unit_price, 2),
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {

                $sheet = $event->sheet;

                $sheet->insertNewRowBefore(1, 6);

                $sheet->setCellValue('A1', 'Summary Report');
                $sheet->setCellValue('A2', "Period: {$this->start} to {$this->end}");
                $sheet->setCellValue('A3', "Total Orders: {$this->summary['totalOrders']}");
                $sheet->setCellValue('A4', "Total Revenue: RM " . number_format($this->summary['totalRevenue'], 2));
                $sheet->setCellValue('A5', "Top Products: {$this->summary['topProducts']}");
                $sheet->setCellValue('A6', "Average Order Value: RM " . number_format($this->summary['avgOrderValue'], 2));

                foreach (range(1, 6) as $row) {
                    $sheet->mergeCells("A{$row}:H{$row}");
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal('left');
                }

                $sheet->getStyle('A7:H7')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => 'center'],
                    'borders' => ['allBorders' => ['borderStyle' => 'thin']],
                ]);


                foreach (range('A', 'H') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $last = $sheet->getHighestRow();
                $sheet->getStyle("A7:H{$last}")->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => 'thin']],
                ]);
            }
        ];
    }
}
