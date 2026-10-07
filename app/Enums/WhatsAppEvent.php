<?php

namespace App\Enums;

enum WhatsAppEvent: string
{
    case Paid = 'PAID';
    case Redeeming = 'REDEEMING';
    case Success = 'SUCCESS';
    case Failed = 'FAILED';
    case Expired = 'EXPIRED';
    case Canceled = 'CANCELED';
}
