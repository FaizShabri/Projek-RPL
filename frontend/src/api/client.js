const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api';

/**
 * Base API client for Pusat Laundry.
 * Wraps fetch with base URL, default headers, and auth token injection.
 */
async function apiClient(endpoint, options = {}) {
  const url = `${API_BASE_URL}${endpoint}`;

  const token = localStorage.getItem('auth_token');
  const activeRole = localStorage.getItem('active_role');

  const defaultHeaders = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...(activeRole ? { 'X-Active-Role': activeRole } : {}),
  };

  const config = {
    ...options,
    headers: {
      ...defaultHeaders,
      ...options.headers,
    },
  };

  const response = await fetch(url, config);

  if (!response.ok) {
    const errorData = await response.json().catch(() => ({}));
    
    // Tangani error spesifik 2FA dari backend tanpa reload halaman penuh
    if (response.status === 403 && errorData.requires_2fa) {
      const err = new Error(errorData.message || '2FA Verification Required');
      err.requires_2fa = true;
      err.error_code = errorData.error_code || '2fa_required';
      err.destination = errorData.destination || '/2fa/challenge';
      err.status = 403;

      // Beritahu aplikasi React via custom event hanya bila saat ini TIDAK berada di /2fa/*
      if (typeof window !== 'undefined' && !window.location.pathname.startsWith('/2fa')) {
        window.dispatchEvent(new CustomEvent('auth:2fa_required', {
          detail: {
            destination: err.destination,
            errorCode: err.error_code,
          }
        }));
      }

      throw err;
    }

    const err = new Error(errorData.reply || errorData.message || `API error: ${response.status}`);
    err.status = response.status;
    err.data = errorData;
    err.errors = errorData.errors;
    throw err;
  }

  return response.json();
}

export const api = {
  get: (endpoint, options = {}) =>
    apiClient(endpoint, { ...options, method: 'GET' }),

  post: (endpoint, data, options = {}) =>
    apiClient(endpoint, { ...options, method: 'POST', body: JSON.stringify(data) }),

  put: (endpoint, data, options = {}) =>
    apiClient(endpoint, { ...options, method: 'PUT', body: JSON.stringify(data) }),

  patch: (endpoint, data, options = {}) =>
    apiClient(endpoint, { ...options, method: 'PATCH', body: JSON.stringify(data) }),

  delete: (endpoint, options = {}) =>
    apiClient(endpoint, { ...options, method: 'DELETE' }),
};

export default api;
