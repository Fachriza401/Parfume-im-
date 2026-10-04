<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Promotion\PromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(
        private readonly AuditLogService $auditLog,
        private readonly NotificationService $notificationService,
    ) {}

    public function adminIndex(Request $request): JsonResponse
    {
        $promotions = Promotion::query()
            ->when($request->filled('search'), fn ($q) => $q->where('code', 'like', '%'.$request->input('search').'%'))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->latest('id')
            ->get();

        return ApiResponse::success(PromotionResource::collection($promotions)->resolve());
    }

    public function store(PromotionRequest $request): JsonResponse
    {
        $data = $this->prepare($request->validated());

        $promotion = Promotion::query()->create($data);
        if ($promotion->is_active) {
            User::customers()->select('id')->each(function (User $customer) use ($promotion): void {
                $discount = $promotion->discount_type->value === 'percentage'
                    ? rtrim(rtrim((string) $promotion->discount_value, '0'), '.').'%'
                    : 'Rp'.number_format((float) $promotion->discount_value, 0, ',', '.');
                $body = $promotion->description ?: "Gunakan kode {$promotion->code} saat checkout.";

                $this->notificationService->notify(
                    $customer,
                    'promotion',
                    "Promo baru: {$promotion->name}",
                    "{$discount} — {$body}",
                    ['promotion_id' => $promotion->id, 'code' => $promotion->code],
                );
            });
        }

        $this->auditLog->record(
            $request,
            action: 'promotion.create',
            module: 'promotion',
            description: "Promo {$promotion->code} dibuat",
            properties: ['code' => $promotion->code]
        );

        return ApiResponse::success(
            (new PromotionResource($promotion))->resolve(),
            'Promo berhasil dibuat',
            201
        );
    }

    public function update(PromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $promotion->update($this->prepare($request->validated()));

        $this->auditLog->record(
            $request,
            action: 'promotion.update',
            module: 'promotion',
            description: "Promo {$promotion->code} diperbarui",
            properties: ['code' => $promotion->code]
        );

        return ApiResponse::success(
            (new PromotionResource($promotion->fresh()))->resolve(),
            'Promo berhasil diperbarui'
        );
    }

    public function destroy(Request $request, Promotion $promotion): JsonResponse
    {
        $code = $promotion->code;
        $promotion->delete();

        $this->auditLog->record(
            $request,
            action: 'promotion.delete',
            module: 'promotion',
            description: "Promo {$code} dihapus",
            properties: ['code' => $code]
        );

        return ApiResponse::success([], 'Promo berhasil dihapus');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepare(array $data): array
    {
        $data['code'] = Promotion::normalizeCode($data['code']);
        $data['name'] ??= $data['code'];

        return $data;
    }
}
