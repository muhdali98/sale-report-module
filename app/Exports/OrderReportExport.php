<?php

namespace App\Exports;

use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class OrderReportExport implements FromCollection, WithEvents
{
    protected $start;
    protected $end;
    protected $orders;
    protected $summary;
    protected $detailStartRow;

    public function __construct($start, $end)
    {
        $this->start = $start;
        $this->end   = $end;

        // Optimized query - eager load all relationships to prevent N+1
        $this->orders = Order::with([
            'customer:id,name,state',
            'orderItems.product:id,name,category_id',
            'orderItems.product.category:id,name'
        ])
            ->when($start, fn($q) => $q->whereDate('order_date', '>=', $start))
            ->when($end, fn($q) => $q->whereDate('order_date', '<=', $end))
            ->orderBy('order_date')
            ->get();

        $this->summary = $this->buildSummary();
    }

    private function buildSummary()
    {
        $totalOrders = $this->orders->count();
        $totalRevenue = 0;
        $productCount = [];

        // Single loop through orders to calculate everything
        foreach ($this->orders as $order) {
            foreach ($order->orderItems as $item) {
                $subtotal = $item->quantity * $item->unit_price;
                $totalRevenue += $subtotal;

                $productName = $item->product->name;
                $productCount[$productName] = ($productCount[$productName] ?? 0) + $item->quantity;
            }
        }

        $avgOrderValue = $totalOrders ? $totalRevenue / $totalOrders : 0;

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

        // Summary Section Header
        $rows->push(['A. Summary Section']);
        $rows->push([]); // Empty row

        // Summary table headers
        $rows->push(['Metric', 'Value']);

        // Summary data
        $rows->push(['Total Orders', $this->summary['totalOrders']]);
        $rows->push(['Total Revenue', 'RM ' . number_format($this->summary['totalRevenue'], 2)]);
        $rows->push(['Top 3 Products', $this->summary['topProducts']]);
        $rows->push(['Average Order Value', 'RM ' . number_format($this->summary['avgOrderValue'], 2)]);

        // Spacing
        $rows->push([]);
        $rows->push([]);

        // Detailed Table Header
        $rows->push(['B. Detailed Table']);
        $rows->push([]); // Empty row

        $this->detailStartRow = $rows->count() + 1;

        // Detailed table column headers
        $rows->push([
            'Order Date',
            'Customer',
            'State',
            'Category',
            'Product',
            'Qty',
            'Unit Price (RM)',
            'Subtotal (RM)'
        ]);

        // Add order details with date grouping
        $currentDate = null;
        $isFirstOrderInDate = true;

        foreach ($this->orders as $order) {
            $orderTotal = 0;
            $orderDate = $order->order_date->format('Y-m-d');

            // Check if this is a new date
            if ($currentDate !== $orderDate) {
                $currentDate = $orderDate;
                $isFirstOrderInDate = true;
            }

            $firstItemInOrder = true;

            foreach ($order->orderItems as $item) {
                $subtotal = $item->quantity * $item->unit_price;
                $orderTotal += $subtotal;

                $rows->push([
                    // Show date only on first item of first order in this date
                    ($firstItemInOrder && $isFirstOrderInDate) ? $orderDate : '',
                    // Show customer only on first item of this order
                    $firstItemInOrder ? $order->customer->name : '',
                    $firstItemInOrder ? ($order->customer->state ?? '-') : '',
                    $item->product->category->name ?? '-',
                    $item->product->name,
                    $item->quantity,
                    number_format($item->unit_price, 2),
                    number_format($subtotal, 2),
                ]);

                $firstItemInOrder = false;
            }

            // Add order total row
            $rows->push([
                'Order #' . $order->order_no . ' Total',
                '',
                '',
                '',
                '',
                '',
                '',
                number_format($orderTotal, 2)
            ]);

            $isFirstOrderInDate = false;
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestRow();

                // A. Summary Section header
                $sheet->mergeCells('A1:H1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
                ]);

                // Summary table headers
                $sheet->getStyle('A2:B2')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D3D3D3']
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_LEFT,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ]);

                // Summary data rows
                $sheet->getStyle('A3:B7')->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ]);

                // Set summary column widths
                $sheet->getColumnDimension('A')->setWidth(25);
                $sheet->getColumnDimension('B')->setWidth(35);

                // === DETAILED TABLE STYLING ===

                // B. Detailed Table header - merge and style
                $sheet->mergeCells('A7:H7');
                $sheet->getStyle('A7')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]
                ]);

                // Detailed table column headers
                $detailHeaderRow = 8;
                $sheet->getStyle("A{$detailHeaderRow}:H{$detailHeaderRow}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'D3D3D3']
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ]);

                // Apply borders to all detail rows
                $sheet->getStyle("A{$detailHeaderRow}:H{$lastRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '000000']
                        ]
                    ],
                    'alignment' => [
                        'vertical' => Alignment::VERTICAL_CENTER
                    ]
                ]);

                // Style order total rows (bold with light gray background)
                for ($row = $detailHeaderRow + 1; $row <= $lastRow; $row++) {
                    $cellValue = $sheet->getCell("A{$row}")->getValue();
                    if (is_string($cellValue) && strpos($cellValue, 'Order #') === 0 && strpos($cellValue, 'Total') !== false) {
                        $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'F0F0F0']
                            ]
                        ]);
                    }
                }

                // Set detailed table column widths
                $sheet->getColumnDimension('C')->setWidth(12);
                $sheet->getColumnDimension('D')->setWidth(15);
                $sheet->getColumnDimension('E')->setWidth(25);
                $sheet->getColumnDimension('F')->setWidth(8);
                $sheet->getColumnDimension('G')->setWidth(18);
                $sheet->getColumnDimension('H')->setWidth(18);

                // Right align numeric columns in detailed table
                $sheet->getStyle("F" . ($detailHeaderRow + 1) . ":H{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                // Center align Qty column
                $sheet->getStyle("F" . ($detailHeaderRow + 1) . ":F{$lastRow}")
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Set row height
                for ($row = 1; $row <= $lastRow; $row++) {
                    $sheet->getRowDimension($row)->setRowHeight(20);
                }
            }
        ];
    }
}
