<?php

namespace App\Enums;

enum VoucherStatus: string
{
    case Available = 'AVAILABLE';
    case Reserved = 'RESERVED';
    case Redeemed = 'REDEEMED';
    case Expired = 'EXPIRED';
    case Failed = 'FAILED';
}
