import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../../api/client';

export default function NotificationPreferences() {
  const [preferences, setPreferences] = useState({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState(null);
  const [error, setError] = useState(null);

  useEffect(() => {
    fetchPreferences();
  }, []);

  const fetchPreferences = async () => {
    setLoading(true);
    try {
      const res = await api.get('/notification-preferences');
      setPreferences(res.preferences || {});
    } catch (err) {
      setError(err.message || 'Gagal memuat preferensi notifikasi.');
    } finally {
      setLoading(false);
    }
  };

  const handleToggle = (key) => {
    const item = preferences[key];
    if (!item || item.critical) return; // Critical tidak boleh diubah

    setPreferences((prev) => ({
      ...prev,
      [key]: {
        ...prev[key],
        is_enabled: !prev[key].is_enabled,
      },
    }));
  };

  const handleSave = async () => {
    setSaving(true);
    setMessage(null);
    setError(null);

    try {
      const payload = {};
      Object.keys(preferences).forEach((key) => {
        payload[key] = preferences[key].is_enabled;
      });

      const res = await api.put('/notification-preferences', { preferences: payload });
      setMessage(res.message || 'Preferensi berhasil disimpan!');
      if (res.preferences) {
        setPreferences(res.preferences);
      }
    } catch (err) {
      setError(err.message || 'Gagal menyimpan preferensi.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="min-h-screen bg-neutral py-8 px-4 sm:px-6 lg:px-8">
      <div className="max-w-3xl mx-auto">
        {/* Breadcrumb & Navigation */}
        <div className="mb-6 flex items-center justify-between">
          <div className="flex items-center gap-2 text-sm text-gray-500">
            <Link to="/" className="hover:text-primary transition-colors">
              Beranda
            </Link>
            <span>/</span>
            <span className="text-gray-800 font-semibold">Pengaturan Notifikasi</span>
          </div>
          <Link
            to="/"
            className="text-xs bg-white border border-gray-200 text-gray-700 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition-colors"
          >
            &larr; Kembali
          </Link>
        </div>

        {/* Card Header */}
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 sm:p-8 mb-6">
          <div className="flex items-center gap-3 mb-2">
            <span className="text-3xl">⚙️</span>
            <div>
              <h1 className="text-xl sm:text-2xl font-bold text-gray-900">
                Pengaturan Preferensi Notifikasi
              </h1>
              <p className="text-sm text-gray-500">
                Kelola jenis pemberitahuan yang ingin Anda terima melalui platform.
              </p>
            </div>
          </div>

          <div className="mt-4 p-3 bg-blue-50 border border-blue-100 rounded-xl text-xs text-blue-800 flex items-start gap-2">
            <span className="text-base">ℹ️</span>
            <div>
              <strong>Notifikasi Kritis:</strong> Pemberitahuan yang bertanda 
              <span className="bg-amber-100 text-amber-800 font-bold px-1.5 py-0.5 rounded-sm mx-1">
                Wajib Aktif (Kritis)
              </span>
              tidak dapat dimatikan demi transparansi transaksi, perlindungan pesanan, dan konfirmasi tagihan COD.
            </div>
          </div>
        </div>

        {/* Alert Messages */}
        {message && (
          <div className="mb-6 p-4 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-sm font-medium flex items-center justify-between">
            <div className="flex items-center gap-2">
              <span>✅</span> {message}
            </div>
            <button onClick={() => setMessage(null)} className="text-emerald-700 font-bold cursor-pointer">
              ✕
            </button>
          </div>
        )}

        {error && (
          <div className="mb-6 p-4 bg-rose-50 border border-rose-200 text-rose-800 rounded-xl text-sm font-medium flex items-center justify-between">
            <div className="flex items-center gap-2">
              <span>⚠️</span> {error}
            </div>
            <button onClick={() => setError(null)} className="text-rose-700 font-bold cursor-pointer">
              ✕
            </button>
          </div>
        )}

        {/* List Preferensi */}
        <div className="bg-white rounded-2xl shadow-sm border border-gray-100 divide-y divide-gray-100 overflow-hidden">
          {loading ? (
            <div className="p-12 text-center text-sm text-gray-500">
              <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto mb-3" />
              Memuat preferensi...
            </div>
          ) : (
            Object.entries(preferences).map(([key, item]) => {
              const isCritical = item.critical;
              const isEnabled = item.is_enabled;

              return (
                <div
                  key={key}
                  className="p-4 sm:p-5 flex items-center justify-between gap-4 hover:bg-gray-50/50 transition-colors"
                >
                  <div className="flex-1 min-w-0 pr-2">
                    <div className="flex items-center gap-2 flex-wrap mb-1">
                      <h4 className="text-sm font-bold text-gray-900">{item.label}</h4>
                      {isCritical ? (
                        <span className="text-[11px] bg-amber-100 text-amber-800 font-semibold px-2 py-0.5 rounded-full border border-amber-200">
                          🔒 Wajib Aktif (Kritis)
                        </span>
                      ) : (
                        <span className="text-[11px] bg-gray-100 text-gray-600 font-medium px-2 py-0.5 rounded-full">
                          Opsional
                        </span>
                      )}
                    </div>
                    <p className="text-xs text-gray-500 leading-relaxed">
                      {item.description}
                    </p>
                  </div>

                  {/* Toggle Switch */}
                  <div className="shrink-0 flex items-center">
                    <button
                      type="button"
                      disabled={isCritical}
                      onClick={() => handleToggle(key)}
                      className={`relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-hidden ${
                        isEnabled ? 'bg-primary' : 'bg-gray-300'
                      } ${isCritical ? 'opacity-70 cursor-not-allowed' : ''}`}
                      role="switch"
                      aria-checked={isEnabled}
                    >
                      <span
                        className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out ${
                          isEnabled ? 'translate-x-5' : 'translate-x-0'
                        }`}
                      />
                    </button>
                  </div>
                </div>
              );
            })
          )}
        </div>

        {/* Action Button */}
        <div className="mt-6 flex items-center justify-end gap-3">
          <Link
            to="/"
            className="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-white transition-colors"
          >
            Batal
          </Link>
          <button
            onClick={handleSave}
            disabled={saving || loading}
            className="px-6 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary-dark transition-all cursor-pointer shadow-sm hover:shadow-md disabled:opacity-50"
          >
            {saving ? 'Menyimpan...' : 'Simpan Perubahan'}
          </button>
        </div>
      </div>
    </div>
  );
}
