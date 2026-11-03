<?php

namespace App\Http\Controllers\Api;

use App\Actions\Order\ProcessCheckoutAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Order\CheckoutRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class OrderController extends Controller
{
    /**
     * Melihat riwayat transaksi penjualan.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Order::query()
            ->with('cashier', 'items')
            ->when($request->filled('invoice_number'), function ($q) use ($request) {
                $q->where('invoice_number', 'like', "{$request->string('invoice_number')}%");
            })
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('cashier_id'), fn ($q) => $q->where('cashier_id', $request->integer('cashier_id')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('created_at', $request->input('date')))
            ->orderByDesc('id');

        return OrderResource::collection($query->paginate(20));
    }

    /**
     * Menampilkan detail satu struk invoice.
     */
    public function show(Order $order): JsonResponse
    {
        return response()->json([
            'data' => new OrderResource($order->load(['cashier', 'items'])),
        ]);
    }

    /**
     * Kasir memproses checkout penjualan.
     */
    public function checkout(CheckoutRequest $request, ProcessCheckoutAction $action): JsonResponse
    {
        $order = $action->execute(
            cashier: $request->user(),
            items: $request->input('items'),
            discountAmount: (float) $request->input('discount_amount', 0),
            taxAmount: (float) $request->input('tax_amount', 0),
            customerName: $request->input('customer_name'),
            notes: $request->input('notes'),
        );

        return response()->json([
            'message' => 'Transaksi penjualan berhasil diproses.',
            'data' => new OrderResource($order),
        ], 201);
    }
}
