/**
 * User roles in Pusat Laundry platform.
 * Matches backend UserRole enum.
 */
export const USER_ROLES = {
  PLATFORM_OWNER: 'platform_owner',
  ADMIN_OWNER: 'admin_owner',
  ADMIN_TOKO: 'admin_toko',
  OWNER_TOKO: 'owner_toko',
  KURIR: 'kurir',
  CUSTOMER: 'customer',
};

/**
 * Human-readable role labels (Bahasa Indonesia).
 */
export const ROLE_LABELS = {
  [USER_ROLES.PLATFORM_OWNER]: 'Platform Owner',
  [USER_ROLES.ADMIN_OWNER]: 'Admin Owner',
  [USER_ROLES.ADMIN_TOKO]: 'Admin Toko',
  [USER_ROLES.OWNER_TOKO]: 'Owner Toko',
  [USER_ROLES.KURIR]: 'Kurir',
  [USER_ROLES.CUSTOMER]: 'Customer',
};
