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

        $totalOrders = Order::whereBetween('order_date', [$start, $end])->count();
        $totalRevenue = Order::whereBetween('order_date', [$start, $end])
            ->sum('total_amount');
        $topProducts = OrderItem::select('products.name as product_name', DB::raw('SUM(order_items.quantity) as total_qty'))
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.order_date', [$start, $end])
            ->groupBy('products.name')
            ->orderByDesc('total_qty')
            ->limit(3)
            ->get();

        $avgOrderValue = Order::whereBetween('order_date', [$start, $end])
            ->avg('total_amount');

        $orders = Order::with(['orderItems','customer', 'orderItems.product.category'])
            ->whereBetween('order_date', [$start, $end])
            ->orderByDesc('order_date')
            ->paginate(10)
            ->withQueryString();

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
