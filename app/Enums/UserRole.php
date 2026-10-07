<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case Operator = 'operator';
    case KabupatenAdmin = 'kabupaten_admin';
    case Customer = 'customer';

    /**
     * Roles allowed to sign into the admin dashboard.
     *
     * @return array<int, self>
     */
    public static function dashboardRoles(): array
    {
        return [self::SuperAdmin, self::Operator, self::KabupatenAdmin];
    }
}
