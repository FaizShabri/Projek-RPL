/**
 * KurirGPSStatus — Komponen status bar kecil untuk dashboard kurir.
 * Menampilkan indikator GPS sharing: aktif, ditolak, tidak tersedia, atau mati.
 */
export default function KurirGPSStatus({
  isTracking,
  lastPing,
  error,
  gpsPermission,
  trackingOrderCount,
}) {
  /**
   * Determine display state:
   * 1. Tracking aktif & no error → hijau pulsing
   * 2. GPS denied → merah
   * 3. GPS unavailable/error → oranye
   * 4. Tidak tracking → abu-abu
   */

  const formatTime = (date) => {
    if (!date) return null;
    return new Date(date).toLocaleTimeString('id-ID', {
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    });
  };

  // State: tracking aktif tanpa error
  if (isTracking && !error) {
    return (
      <div className="flex items-center gap-2.5 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-2.5 rounded-xl text-xs font-semibold sticky top-0 z-10">
        {/* Pulsing green dot */}
        <span className="relative flex h-2.5 w-2.5">
          <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
          <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500" />
        </span>
        <span>
          📡 Lokasi Aktif ({trackingOrderCount} order)
        </span>
        {lastPing && (
          <span className="text-emerald-600 font-normal ml-auto">
            Ping terakhir: {formatTime(lastPing)}
          </span>
        )}
      </div>
    );
  }

  // State: GPS denied by user
  if (error && gpsPermission === 'denied') {
    return (
      <div className="flex items-center gap-2.5 bg-rose-50 border border-rose-200 text-rose-800 px-4 py-2.5 rounded-xl text-xs font-semibold sticky top-0 z-10">
        <span className="relative flex h-2.5 w-2.5">
          <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500" />
        </span>
        <span>
          ❌ GPS Ditolak — Aktifkan izin lokasi di pengaturan browser
        </span>
      </div>
    );
  }

  // State: GPS error (unavailable, timeout, etc.)
  if (error) {
    return (
      <div className="flex items-center gap-2.5 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-2.5 rounded-xl text-xs font-semibold sticky top-0 z-10">
        <span className="relative flex h-2.5 w-2.5">
          <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500" />
        </span>
        <span>
          ⚠️ GPS Tidak Tersedia — {error}
        </span>
      </div>
    );
  }

  // State: not tracking (no trackable orders)
  return (
    <div className="flex items-center gap-2.5 bg-gray-50 border border-gray-200 text-gray-500 px-4 py-2.5 rounded-xl text-xs font-medium sticky top-0 z-10">
      <span className="relative flex h-2.5 w-2.5">
        <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-gray-400" />
      </span>
      <span>
        Tidak ada tugas aktif — tracking lokasi mati
      </span>
    </div>
  );
}
