<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel;
use App\Exports\OrderReportExport;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $start = $request->start_date ?? now()->subDays(30)->format('Y-m-d');
        $end = $request->end_date ?? now()->format('Y-m-d');

        $totalOrders = OrderItem::whereHas('order', fn($q) => $q->whereBetween('order_date', [$start, $end]))->count();
        $totalRevenue = OrderItem::whereHas('order', fn($q) => $q->whereBetween('order_date', [$start, $end]))
            ->sum(DB::raw('quantity * unit_price'));

        $topProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_qty'))
            ->whereHas('order', fn($q) => $q->whereBetween('order_date', [$start, $end]))
            ->groupBy('product_id')
            ->orderByDesc('total_qty')
            ->with('product')
            ->take(3)
            ->get();

        $avgOrderValue = OrderItem::whereHas('order', fn($q) => $q->whereBetween('order_date', [$start, $end]))
            ->select(DB::raw('AVG(quantity * unit_price) as avg_value'))
            ->value('avg_value');

        $orders = OrderItem::with(['order.customer', 'product.category'])
            ->whereHas('order', fn($q) => $q->whereBetween('order_date', [$start, $end]))
            ->orderByDesc('order_id')
            ->paginate(10);

        return view('report.index', compact(
            'totalOrders',
            'totalRevenue',
            'topProducts',
            'avgOrderValue',
            'orders',
            'start',
            'end'
        ));
    }

    public function export(Request $request)
    {
        return Excel::download(
            new OrderReportExport($request->start_date, $request->end_date),
            'order_report.xlsx'
        );
    }


}
