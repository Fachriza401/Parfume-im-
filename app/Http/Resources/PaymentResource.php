<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_method' => $this->payment_method->value,
            'payment_method_label' => $this->payment_method->label(),
            'payment_status' => $this->payment_status->value,
            'payment_status_label' => $this->payment_status->label(),
            'transaction_id' => $this->transaction_id,
            'amount' => (float) $this->amount,
            'paid_at' => optional($this->paid_at)->toISOString(),
            'refunded_at' => optional($this->refunded_at)->toISOString(),
            'destination_name' => $this->meta['destination_name'] ?? null,
            'destination_account' => $this->meta['destination_account'] ?? null,
            'destination_account_name' => $this->meta['destination_account_name'] ?? null,
            'receipt_uploaded' => ! empty($this->meta['receipt_path']),
            'receipt_uploaded_at' => $this->meta['receipt_uploaded_at'] ?? null,
        ];
    }
}
