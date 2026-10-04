<?php

namespace App\Http\Controllers\Api\V1\Order;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PaymentReceiptController extends Controller
{
    public function store(Request $request, Order $order): JsonResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 404);
        abort_unless($order->status_payment->value === 'unpaid', 422, 'Pesanan ini tidak menunggu pembayaran.');
        abort_unless($order->status_order->value === 'pending', 422, 'Pesanan ini tidak menunggu pembayaran.');

        $payment = $order->payment;
        abort_unless(
            $payment !== null && in_array($payment->payment_method->value, ['bank_transfer', 'e_wallet'], true),
            422,
            'Metode pembayaran ini tidak memerlukan bukti transfer.'
        );

        $validated = $request->validate([
            'receipt' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $validated['receipt']->store('payment-receipts', 'local');
        $meta = $payment->meta ?? [];
        $previousPath = $meta['receipt_path'] ?? null;
        $meta['receipt_path'] = $path;
        $meta['receipt_uploaded_at'] = now()->toISOString();
        $payment->update(['meta' => $meta]);

        if (is_string($previousPath) && $previousPath !== $path) {
            Storage::disk('local')->delete($previousPath);
        }

        return ApiResponse::success([
            'receipt_uploaded' => true,
            'receipt_uploaded_at' => $meta['receipt_uploaded_at'],
        ], 'Bukti pembayaran berhasil diunggah');
    }

    public function show(Order $order): BinaryFileResponse
    {
        $path = $order->payment?->meta['receipt_path'] ?? null;
        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path));
    }
}
