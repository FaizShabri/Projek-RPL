import { useState, useEffect, useRef, useCallback } from 'react';
import api from '../../api/client';

/**
 * KurirTrackingMap — Komponen peta real-time posisi kurir.
 * Menggunakan Google Maps JS API (raw script tag, tanpa npm package).
 * Fallback ke tampilan teks jika API key belum dikonfigurasi.
 *
 * @param {{ orderId: number|string, isVisible: boolean }} props
 */
export default function KurirTrackingMap({ orderId, isVisible }) {
  const POLL_INTERVAL_MS = 5000;

  const [location, setLocation] = useState(null); // { lat, lng, status, updated_at, kurir_name, order_status }
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [mapLoaded, setMapLoaded] = useState(false);
  const [trackingInactive, setTrackingInactive] = useState(false);

  const mapContainerRef = useRef(null);
  const mapInstanceRef = useRef(null);
  const markerRef = useRef(null);
  const pulseOverlayRef = useRef(null);
  const pollIntervalRef = useRef(null);

  const apiKey = import.meta.env.VITE_GOOGLE_MAPS_API_KEY;
  const hasValidApiKey = apiKey && apiKey !== 'your-google-maps-api-key';

  // ─── Load Google Maps Script ───
  useEffect(() => {
    if (!hasValidApiKey) return;

    if (window.google && window.google.maps) {
      setMapLoaded(true);
      return;
    }

    const existingScript = document.getElementById('google-maps-script');
    if (!existingScript) {
      const script = document.createElement('script');
      script.id = 'google-maps-script';
      script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places`;
      script.async = true;
      script.defer = true;
      script.onload = () => setMapLoaded(true);
      script.onerror = () => setMapLoaded(false);
      document.head.appendChild(script);
    } else {
      if (window.google && window.google.maps) {
        setMapLoaded(true);
      } else {
        existingScript.addEventListener('load', () => setMapLoaded(true));
      }
    }
  }, [apiKey, hasValidApiKey]);

  // ─── Fetch tracking data ───
  const fetchTracking = useCallback(async () => {
    if (!orderId) return;

    try {
      const res = await api.get(`/customer/orders/${orderId}/tracking`);

      if (res.tracking_active === false) {
        setTrackingInactive(true);
        setLoading(false);
        return;
      }

      setTrackingInactive(false);
      setLocation({
        lat: parseFloat(res.latitude),
        lng: parseFloat(res.longitude),
        status: res.status, // 'live' | 'stale' | 'offline'
        updated_at: res.updated_at,
        kurir_name: res.kurir_name,
        order_status: res.order_status,
      });
      setError(null);
      setLoading(false);
    } catch (err) {
      console.warn('[KurirTrackingMap] Fetch error:', err);
      setError('Gagal memuat data tracking');
      setLoading(false);
    }
  }, [orderId]);

  // ─── Polling effect ───
  useEffect(() => {
    if (!isVisible || !orderId) {
      if (pollIntervalRef.current) {
        clearInterval(pollIntervalRef.current);
        pollIntervalRef.current = null;
      }
      return;
    }

    // Initial fetch
    setLoading(true);
    fetchTracking();

    // Set up polling
    pollIntervalRef.current = setInterval(fetchTracking, POLL_INTERVAL_MS);

    return () => {
      if (pollIntervalRef.current) {
        clearInterval(pollIntervalRef.current);
        pollIntervalRef.current = null;
      }
    };
  }, [isVisible, orderId, fetchTracking]);

  // ─── Initialize & update Google Map ───
  useEffect(() => {
    if (!hasValidApiKey || !mapLoaded || !location || !mapContainerRef.current) return;
    if (trackingInactive) return;

    const pos = { lat: location.lat, lng: location.lng };

    // Initialize map if not yet created
    if (!mapInstanceRef.current) {
      mapInstanceRef.current = new window.google.maps.Map(mapContainerRef.current, {
        center: pos,
        zoom: 16,
        disableDefaultUI: true,
        zoomControl: true,
        gestureHandling: 'greedy',
        styles: [
          { featureType: 'poi', stylers: [{ visibility: 'off' }] },
          { featureType: 'transit', stylers: [{ visibility: 'off' }] },
        ],
      });
    }

    // Build marker icon based on status
    const markerColor =
      location.status === 'live'
        ? '#2ECC71'
        : location.status === 'stale'
        ? '#F59E0B'
        : '#9CA3AF';

    const markerIcon = {
      path: window.google.maps.SymbolPath.CIRCLE,
      fillColor: markerColor,
      fillOpacity: 1,
      strokeColor: '#FFFFFF',
      strokeWeight: 3,
      scale: 10,
    };

    // Create or update marker
    if (!markerRef.current) {
      markerRef.current = new window.google.maps.Marker({
        position: pos,
        map: mapInstanceRef.current,
        icon: markerIcon,
        title: location.kurir_name || 'Kurir',
      });
    } else {
      markerRef.current.setPosition(pos);
      markerRef.current.setIcon(markerIcon);
    }

    // Smooth pan to new position (don't re-center abruptly)
    mapInstanceRef.current.panTo(pos);
  }, [hasValidApiKey, mapLoaded, location, trackingInactive]);

  // ─── Cleanup map on unmount ───
  useEffect(() => {
    return () => {
      if (markerRef.current) {
        markerRef.current.setMap(null);
        markerRef.current = null;
      }
      mapInstanceRef.current = null;
    };
  }, []);

  // ─── Status badge helper ───
  const renderStatusBadge = (status) => {
    switch (status) {
      case 'live':
        return (
          <span className="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-full">
            <span className="relative flex h-1.5 w-1.5">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
              <span className="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500" />
            </span>
            LIVE
          </span>
        );
      case 'stale':
        return (
          <span className="inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-100 px-2 py-0.5 rounded-full">
            <span className="inline-flex rounded-full h-1.5 w-1.5 bg-amber-500" />
            STALE
          </span>
        );
      case 'offline':
        return (
          <span className="inline-flex items-center gap-1 text-[10px] font-bold text-gray-600 bg-gray-100 px-2 py-0.5 rounded-full">
            <span className="inline-flex rounded-full h-1.5 w-1.5 bg-gray-400" />
            OFFLINE
          </span>
        );
      default:
        return null;
    }
  };

  const formatUpdatedAt = (dateStr) => {
    if (!dateStr) return '-';
    return new Date(dateStr).toLocaleTimeString('id-ID', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  };

  // ─── Render: Loading state ───
  if (loading) {
    return (
      <div className="rounded-xl border border-gray-200 shadow-sm overflow-hidden bg-white">
        <div className="h-[250px] md:h-[350px] flex items-center justify-center bg-neutral">
          <div className="text-center space-y-2">
            <div className="w-6 h-6 border-3 border-secondary border-t-transparent rounded-full animate-spin mx-auto" />
            <p className="text-xs text-gray-500">Memuat tracking kurir...</p>
          </div>
        </div>
      </div>
    );
  }

  // ─── Render: Tracking inactive ───
  if (trackingInactive) {
    return (
      <div className="rounded-xl border border-gray-200 shadow-sm overflow-hidden bg-white">
        <div className="h-[200px] flex items-center justify-center bg-neutral">
          <div className="text-center space-y-2 px-6">
            <div className="text-3xl">📍</div>
            <p className="text-sm font-bold text-gray-700">Tracking tidak aktif</p>
            <p className="text-xs text-gray-500">
              Kurir belum memulai perjalanan atau tracking sedang tidak tersedia untuk pesanan ini.
            </p>
          </div>
        </div>
      </div>
    );
  }

  // ─── Render: Error state ───
  if (error && !location) {
    return (
      <div className="rounded-xl border border-rose-200 shadow-sm overflow-hidden bg-rose-50">
        <div className="h-[200px] flex items-center justify-center">
          <div className="text-center space-y-2 px-6">
            <div className="text-3xl">⚠️</div>
            <p className="text-sm font-bold text-rose-700">{error}</p>
            <p className="text-xs text-rose-500">
              Coba muat ulang halaman atau periksa koneksi internet Anda.
            </p>
          </div>
        </div>
      </div>
    );
  }

  // ─── Render: Fallback (no Google Maps API key) ───
  if (!hasValidApiKey) {
    return (
      <div className="rounded-xl border border-gray-200 shadow-sm overflow-hidden bg-white">
        <div className="bg-neutral p-5 space-y-3">
          <div className="flex items-center justify-between">
            <p className="text-xs font-bold text-gray-700 flex items-center gap-1.5">
              📍 Lokasi Kurir Real-time
            </p>
            {location && renderStatusBadge(location.status)}
          </div>

          {location ? (
            <div className="space-y-2">
              <div className="grid grid-cols-2 gap-3">
                <div className="bg-white p-3 rounded-lg border border-gray-200">
                  <p className="text-[10px] uppercase font-bold text-gray-400">Latitude</p>
                  <p className="text-sm font-mono font-bold text-gray-800">
                    {location.lat.toFixed(6)}
                  </p>
                </div>
                <div className="bg-white p-3 rounded-lg border border-gray-200">
                  <p className="text-[10px] uppercase font-bold text-gray-400">Longitude</p>
                  <p className="text-sm font-mono font-bold text-gray-800">
                    {location.lng.toFixed(6)}
                  </p>
                </div>
              </div>

              {location.kurir_name && (
                <div className="flex items-center gap-2 text-xs text-gray-600">
                  <span>🛵</span>
                  <span className="font-semibold text-gray-800">{location.kurir_name}</span>
                </div>
              )}

              <div className="flex items-center justify-between text-xs text-gray-500">
                <span>Update terakhir: {formatUpdatedAt(location.updated_at)}</span>
                {location.status === 'stale' && (
                  <span className="text-amber-600 font-semibold">
                    ⚠️ Lokasi mungkin tidak akurat
                  </span>
                )}
                {location.status === 'offline' && (
                  <span className="text-gray-500 font-semibold">
                    Kurir sedang offline
                  </span>
                )}
              </div>
            </div>
          ) : (
            <p className="text-xs text-gray-500">Data lokasi belum tersedia.</p>
          )}

          <div className="bg-amber-50 border border-amber-200 rounded-lg p-2.5 text-xs text-amber-700">
            🗺️ Peta tidak tersedia — API Key Google Maps belum dikonfigurasi
          </div>
        </div>
      </div>
    );
  }

  // ─── Render: Google Maps with status bar ───
  return (
    <div className="rounded-xl border border-gray-200 shadow-sm overflow-hidden bg-white">
      {/* Map Container */}
      <div
        ref={mapContainerRef}
        className="h-[250px] md:h-[350px] w-full bg-neutral"
      />

      {/* Status Bar */}
      {location && (
        <div className="flex items-center justify-between px-4 py-2.5 bg-white border-t border-gray-100">
          <div className="flex items-center gap-2">
            {location.kurir_name && (
              <span className="text-xs font-semibold text-gray-800 flex items-center gap-1">
                🛵 {location.kurir_name}
              </span>
            )}
            {renderStatusBadge(location.status)}
          </div>

          <div className="flex items-center gap-2 text-[11px] text-gray-500">
            {location.status === 'stale' && (
              <span className="text-amber-600 font-medium">
                Lokasi mungkin tidak akurat
              </span>
            )}
            {location.status === 'offline' && (
              <span className="text-gray-500 font-medium">
                Kurir sedang offline
              </span>
            )}
            <span>
              {formatUpdatedAt(location.updated_at)}
            </span>
          </div>
        </div>
      )}
    </div>
  );
}
