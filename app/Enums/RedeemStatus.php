<?php

namespace App\Enums;

enum RedeemStatus: string
{
    case Pending = 'PENDING';
    case Processing = 'PROCESSING';
    case Success = 'SUCCESS';
    case Failed = 'FAILED';
    case Canceled = 'CANCELED';
}
