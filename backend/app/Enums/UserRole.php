<?php

namespace App\Enums;

enum UserRole: string
{
    case PLATFORM_OWNER = 'platform_owner';
    case ADMIN_OWNER = 'admin_owner';
    case ADMIN_TOKO = 'admin_toko';
    case OWNER_TOKO = 'owner_toko';
    case KURIR = 'kurir';
    case CUSTOMER = 'customer';
}
