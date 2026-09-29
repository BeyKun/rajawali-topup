<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'UNPAID';
    case Paid = 'PAID';
    case Expired = 'EXPIRED';
    case Failed = 'FAILED';
    case Canceled = 'CANCELED';
    case Refunded = 'REFUNDED';
    case RefundPending = 'REFUND_PENDING';
}
