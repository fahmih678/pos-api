<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Ringkasan penjualan dan laba kotor toko (Omzet, HPP, Profit, & Payment Breakdown).
     */
    public function salesSummary(Request $request): JsonResponse
    {
        // Security Check: Hanya Admin dan Manager yang berhak melihat laporan keuangan
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            abort(403, 'Akses laporan finansial ditolak.');
        }

        $startDate = $request->input('start_date', now()->startOfDay()->toDateTimeString());
        $endDate = $request->input('end_date', now()->endOfDay()->toDateTimeString());

        // 1. Query Agregasi Order yang berhasil (Completed)
        $orderMetrics = Order::query()
            ->where('status', OrderStatus::Completed)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->selectRaw('
                COUNT(id) as total_orders,
                COALESCE(SUM(subtotal), 0) as total_subtotal,
                COALESCE(SUM(discount_amount), 0) as total_discount,
                COALESCE(SUM(tax_amount), 0) as total_tax,
                COALESCE(SUM(grand_total), 0) as total_revenue
            ')
            ->first();

        // 2. Query Agregasi HPP (Modal) berdasarkan Snapshot pada item order
        $totalCost = (float) OrderItem::query()
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->where('status', OrderStatus::Completed)
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->selectRaw('COALESCE(SUM(unit_cost_price * quantity), 0) as total_cost')
            ->value('total_cost');

        $totalRevenue = (float) $orderMetrics->total_revenue;
        $grossProfit = $totalRevenue - $totalCost;

        // 3. Rekap Penerimaan berdasarkan Metode Pembayaran
        $paymentBreakdown = Payment::query()
            ->where('status', PaymentStatus::Success)
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->where('status', OrderStatus::Completed)
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->select('payment_method')
            ->selectRaw('COUNT(id) as count, SUM(amount_tendered - change_due) as total_amount')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($item) => [
                'payment_method' => $item->payment_method->value,
                'payment_method_label' => $item->payment_method->label(),
                'transaction_count' => (int) $item->count,
                'total_amount' => (float) $item->total_amount,
            ]);

        return response()->json([
            'data' => [
                'period' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                ],
                'summary' => [
                    'total_orders' => (int) $orderMetrics->total_orders,
                    'total_revenue' => $totalRevenue,
                    'total_cogs_modal' => $totalCost,
                    'gross_profit' => $grossProfit,
                    'total_discount' => (float) $orderMetrics->total_discount,
                    'total_tax' => (float) $orderMetrics->total_tax,
                ],
                'payment_methods_breakdown' => $paymentBreakdown,
            ],
        ]);
    }

    /**
     * Menampilkan daftar produk terlaris berdasarkan kuantitas yang terjual.
     */
    public function topSellingProducts(Request $request): JsonResponse
    {
        // Security Check: Hanya Admin dan Manager yang berhak melihat laporan keuangan
        if (! $request->user()->isAdmin() && ! $request->user()->isManager()) {
            abort(403, 'Akses laporan finansial ditolak.');
        }

        $startDate = $request->input('start_date', now()->subDays(30)->startOfDay()->toDateTimeString());
        $endDate = $request->input('end_date', now()->endOfDay()->toDateTimeString());
        $limit = min($request->integer('limit', 10), 50);
        $topProducts = OrderItem::query()
            ->whereHas('order', function ($query) use ($startDate, $endDate) {
                $query->where('status', OrderStatus::Completed)
                    ->whereBetween('created_at', [$startDate, $endDate]);
            })
            ->select('product_id', 'product_name_snapshot')
            ->selectRaw('
                SUM(quantity) as total_sold,
                SUM(subtotal) as total_revenue
            ')
            ->groupBy('product_id', 'product_name_snapshot')
            ->orderByDesc('total_sold')
            ->limit($limit)
            ->get()
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name_snapshot,
                'total_quantity_sold' => (int) $item->total_sold,
                'total_revenue' => (float) $item->total_revenue,
            ]);

        return response()->json([
            'data' => $topProducts,
        ]);
    }
}
