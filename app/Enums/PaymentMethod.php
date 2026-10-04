<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BANK_TRANSFER = 'bank_transfer';
    case E_WALLET = 'e_wallet';
    case COD = 'cod';

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
            self::BANK_TRANSFER => 'Transfer Bank',
            self::E_WALLET => 'E-Wallet',
            self::COD => 'Bayar di Tempat',
        };
    }
}
