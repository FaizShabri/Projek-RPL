import { useState, useEffect, useRef, useCallback } from 'react';
import api from '../api/client';

/**
 * useKurirGPS — Hook untuk auto-sharing lokasi GPS kurir ke backend.
 *
 * Digunakan di KurirDashboard. Secara otomatis memulai watchPosition
 * ketika terdapat order dengan status trackable ('dijemput', 'diantar'),
 * dan berhenti ketika tidak ada order yang perlu di-track (privasi terjaga).
 *
 * @param {Array} activeOrders - Array order milik kurir (semua status)
 * @returns {{ isTracking: boolean, lastPing: Date|null, error: string|null, gpsPermission: string|null, trackingOrderCount: number }}
 */
export function useKurirGPS(activeOrders = []) {
  const TRACKABLE_STATUSES = ['dijemput', 'diantar'];
  const THROTTLE_MS = 8000; // Max 1 ping per 8 detik per order

  const [isTracking, setIsTracking] = useState(false);
  const [lastPing, setLastPing] = useState(null);
  const [error, setError] = useState(null);
  const [gpsPermission, setGpsPermission] = useState(null);

  const watchIdRef = useRef(null);
  const lastPingTimeRef = useRef({}); // { [orderId]: timestamp }
  const trackableOrdersRef = useRef([]);

  // Filter order yang statusnya bisa di-track
  const trackableOrders = (activeOrders || []).filter(
    (order) => order && TRACKABLE_STATUSES.includes(order.status)
  );
  const trackingOrderCount = trackableOrders.length;

  // Keep ref in sync for use inside geolocation callback (avoid stale closure)
  useEffect(() => {
    trackableOrdersRef.current = trackableOrders;
  }, [trackableOrders.map((o) => o.id).join(',')]); // eslint-disable-line react-hooks/exhaustive-deps

  // Check GPS permission status via Permissions API (if available)
  useEffect(() => {
    if (navigator.permissions && navigator.permissions.query) {
      navigator.permissions
        .query({ name: 'geolocation' })
        .then((result) => {
          setGpsPermission(result.state); // 'granted' | 'denied' | 'prompt'
          result.addEventListener('change', () => {
            setGpsPermission(result.state);
          });
        })
        .catch(() => {
          // Permissions API not supported for geolocation in some browsers
          setGpsPermission(null);
        });
    }
  }, []);

  /**
   * Kirim ping lokasi ke backend untuk setiap trackable order.
   * Throttle: max 1 ping per 8 detik per order_id.
   */
  const sendPings = useCallback((latitude, longitude) => {
    const now = Date.now();
    const orders = trackableOrdersRef.current;

    orders.forEach((order) => {
      const lastTime = lastPingTimeRef.current[order.id] || 0;
      if (now - lastTime < THROTTLE_MS) return; // throttled

      lastPingTimeRef.current[order.id] = now;

      // Fire and forget — catch errors silently, just update error state
      api
        .post('/kurir/location/ping', {
          latitude,
          longitude,
          order_id: order.id,
        })
        .then(() => {
          setError(null);
          setLastPing(new Date());
        })
        .catch((err) => {
          console.warn('[useKurirGPS] Ping gagal untuk order', order.id, err);
          setError('Gagal mengirim lokasi ke server');
          // Still update lastPing so UI knows we tried
          setLastPing(new Date());
        });
    });
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // Main effect: start/stop geolocation watching based on trackable orders
  useEffect(() => {
    // No trackable orders → stop watching
    if (trackingOrderCount === 0) {
      if (watchIdRef.current !== null) {
        navigator.geolocation.clearWatch(watchIdRef.current);
        watchIdRef.current = null;
      }
      setIsTracking(false);
      return;
    }

    // Check if geolocation API is available
    if (!navigator.geolocation) {
      setError('GPS tidak didukung oleh browser ini');
      setIsTracking(false);
      return;
    }

    // Already watching → nothing to do
    if (watchIdRef.current !== null) return;

    // Start watching
    const id = navigator.geolocation.watchPosition(
      (position) => {
        const { latitude, longitude } = position.coords;
        setIsTracking(true);
        setError(null);
        setGpsPermission('granted');
        sendPings(latitude, longitude);
      },
      (geoError) => {
        setIsTracking(false);
        switch (geoError.code) {
          case geoError.PERMISSION_DENIED:
            setError('Izin lokasi ditolak oleh pengguna');
            setGpsPermission('denied');
            break;
          case geoError.POSITION_UNAVAILABLE:
            setError('Lokasi GPS tidak tersedia');
            break;
          case geoError.TIMEOUT:
            setError('Permintaan lokasi timeout');
            break;
          default:
            setError('Gagal mendapatkan lokasi GPS');
            break;
        }
      },
      {
        enableHighAccuracy: true,
        maximumAge: 5000,
        timeout: 15000,
      }
    );

    watchIdRef.current = id;

    // Cleanup on unmount or when trackingOrderCount changes to 0
    return () => {
      if (watchIdRef.current !== null) {
        navigator.geolocation.clearWatch(watchIdRef.current);
        watchIdRef.current = null;
      }
      setIsTracking(false);
    };
  }, [trackingOrderCount, sendPings]);

  // Cleanup stale order IDs from throttle map
  useEffect(() => {
    const activeIds = new Set(trackableOrders.map((o) => o.id));
    Object.keys(lastPingTimeRef.current).forEach((key) => {
      if (!activeIds.has(Number(key)) && !activeIds.has(key)) {
        delete lastPingTimeRef.current[key];
      }
    });
  }, [trackingOrderCount]); // eslint-disable-line react-hooks/exhaustive-deps

  return {
    isTracking,
    lastPing,
    error,
    gpsPermission,
    trackingOrderCount,
  };
}
