<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Operator = 'operator';
    case Customer = 'customer';
}
