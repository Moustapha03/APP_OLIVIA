<?php

namespace App\Enums;

/**
 * How a payment was made. At the desk: cash, or a Mobile Money transfer to
 * the company number, recorded with its transfer reference. Online: the
 * aggregator payment page. Expenses may also be paid by bank transfer or cheque.
 */
enum PaymentMethod: string
{
    case Cash = 'cash';
    case Wave = 'wave';
    case OrangeMoney = 'orange_money';
    case MtnMomo = 'mtn_momo';
    case MoovMoney = 'moov_money';
    case Online = 'online';
    // Expenses only (suppliers), never offered for sales.
    case BankTransfer = 'bank_transfer';
    case Cheque = 'cheque';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Espèces',
            self::Wave => 'Wave',
            self::OrangeMoney => 'Orange Money',
            self::MtnMomo => 'MTN MoMo',
            self::MoovMoney => 'Moov Money',
            self::Online => 'En ligne',
            self::BankTransfer => 'Virement bancaire',
            self::Cheque => 'Chèque',
        };
    }

    /**
     * Mobile Money transfer: a reference is required, the money is not in the drawer.
     */
    public function isTransfer(): bool
    {
        return in_array($this, [self::Wave, self::OrangeMoney, self::MtnMomo, self::MoovMoney], true);
    }

    /**
     * @return list<self>
     */
    public static function atCounter(): array
    {
        return [self::Cash, self::Wave, self::OrangeMoney, self::MtnMomo, self::MoovMoney];
    }

    /**
     * How an agency expense can be paid.
     *
     * @return list<self>
     */
    public static function forExpenses(): array
    {
        return [...self::atCounter(), self::BankTransfer, self::Cheque];
    }
}
