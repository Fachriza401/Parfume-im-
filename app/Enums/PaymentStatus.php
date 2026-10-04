<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case UNPAID = 'unpaid';
    case PAID = 'paid';
    case REFUNDED = 'refunded';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::UNPAID => 'Belum Dibayar',
            self::PAID => 'Lunas',
            self::REFUNDED => 'Dikembalikan',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::UNPAID => $target === self::PAID,
            self::PAID => $target === self::REFUNDED,
            self::REFUNDED => false,
        };
    }
}
