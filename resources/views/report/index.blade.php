<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Order Report') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Container --}}
            <div class="bg-white shadow-sm sm:rounded-lg p-6 space-y-6">

                {{-- Date Filter --}}
                <form method="GET" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Start Date</label>
                        <input type="date" name="start_date" value="{{ $start }}"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">End Date</label>
                        <input type="date" name="end_date" value="{{ $end }}"
                            class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                    </div>
                    <div>
                        <button type="submit"
                            class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Filter</button>
                    </div>
                    <a href="{{ route('report.export', ['start_date' => $start, 'end_date' => $end]) }}"
                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                        Download Excel
                    </a>

                </form>

                {{-- Summary Section --}}
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-gray-50 shadow rounded-lg p-4 text-center">
                        <h5 class="text-gray-500 font-medium">Total Orders</h5>
                        <p class="text-2xl font-bold">{{ $totalOrders }}</p>
                    </div>
                    <div class="bg-gray-50 shadow rounded-lg p-4 text-center">
                        <h5 class="text-gray-500 font-medium">Total Revenue</h5>
                        <p class="text-2xl font-bold">RM{{ number_format($totalRevenue, 2) }}</p>
                    </div>
                    <div class="bg-gray-50 shadow rounded-lg p-4 text-center">
                        <h5 class="text-gray-500 font-medium">Average Order Value</h5>
                        <p class="text-2xl font-bold">RM{{ number_format($avgOrderValue, 2) }}</p>
                    </div>
                    <div class="bg-gray-50 shadow rounded-lg p-4">
                        <h5 class="text-gray-500 font-medium mb-2">Top 3 Products</h5>
                        <ul class="list-disc list-inside text-sm">
                            @foreach($topProducts as $product)
                                <li>{{ $product->product->name ?? '-' }} ({{ $product->total_qty }})</li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                {{-- Detailed Table --}}
                <div class="overflow-x-auto mt-6 border border-gray-200 rounded-lg shadow-sm">
                    <table class="min-w-full divide-y divide-gray-200 bg-white">
                        <thead class="bg-gray-50">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Order Date</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Customer</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    State</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Category</th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Product</th>
                                <th
                                    class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Quantity</th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Unit Price</th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Subtotal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($orders as $item)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        {{ $item->order->created_at->format('Y-m-d') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        {{ $item->order->customer->name }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        {{ $item->order->customer->state ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                        {{ $item->product->category->name ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">{{ $item->product->name }}
                                    </td>
                                    <td class="px-6 py-4 text-center whitespace-nowrap text-sm text-gray-700">
                                        {{ $item->quantity }}
                                    </td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap text-sm text-gray-700">
                                        RM{{ number_format($item->unit_price, 2) }}</td>
                                    <td class="px-6 py-4 text-right whitespace-nowrap text-sm text-gray-700">
                                        RM{{ number_format($item->quantity * $item->unit_price, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4 px-4 py-2 bg-white text-gray-700 rounded">
                        {{ $orders->links() }}
                    </div>
                </div>

            </div>
        </div>
    </div>

</x-app-layout>
