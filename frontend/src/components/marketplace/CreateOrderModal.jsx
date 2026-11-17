import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../api/client';

export default function CreateOrderModal({ toko, isOpen, onClose }) {
  const navigate = useNavigate();
  const [selectedTarifId, setSelectedTarifId] = useState(
    toko?.tarifs && toko.tarifs.length > 0 ? toko.tarifs[0].id : ''
  );
  const [alamatJemput, setAlamatJemput] = useState('');
  const [catatan, setCatatan] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState(null);
  const [isDetectingGps, setIsDetectingGps] = useState(false);

  if (!isOpen || !toko) return null;

  const handleDetectGPS = () => {
    if (!navigator.geolocation) {
      alert('Browser Anda tidak mendukung deteksi lokasi.');
      return;
    }
    setIsDetectingGps(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setIsDetectingGps(false);
        setAlamatJemput(`Lokasi GPS (${pos.coords.latitude.toFixed(5)}, ${pos.coords.longitude.toFixed(5)})`);
      },
      (err) => {
        setIsDetectingGps(false);
        alert('Gagal mendeteksi GPS: ' + err.message);
      }
    );
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    if (!selectedTarifId) {
      setError('Silakan pilih salah satu layanan tarif.');
      return;
    }
    if (!alamatJemput.trim()) {
      setError('Alamat jemput wajib diisi.');
      return;
    }

    try {
      setSubmitting(true);
      setError(null);

      const res = await api.post('/customer/orders', {
        toko_id: toko.id,
        tarif_id: selectedTarifId,
        alamat_jemput: alamatJemput.trim(),
        catatan: catatan.trim() || null,
      });

      onClose();
      // Redirect ke halaman daftar pesanan customer
      navigate('/customer/orders');
    } catch (err) {
      console.error('Error creating order:', err);
      setError(err.message || 'Gagal membuat pesanan.');
    } finally {
      setSubmitting(false);
    }
  };

  const selectedTarif = toko.tarifs?.find((t) => t.id === Number(selectedTarifId));

  return (
    <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4 overflow-y-auto">
      <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 my-8">
        {/* Header Modal */}
        <div className="flex items-start justify-between border-b border-gray-100 pb-3">
          <div>
            <span className="text-xs font-bold text-secondary uppercase tracking-wider">
              Formulir Order Laundry
            </span>
            <h3 className="text-lg font-bold text-gray-900 mt-0.5">
              Pesan ke {toko.nama}
            </h3>
          </div>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 font-bold text-lg p-1"
          >
            ✕
          </button>
        </div>

        {error && (
          <div className="bg-rose-50 border border-rose-200 text-rose-800 text-xs p-3 rounded-xl">
            ⚠️ {error}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Pilih Layanan / Tarif */}
          <div>
            <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
              Pilih Layanan & Tarif <span className="text-rose-500">*</span>
            </label>
            {toko.tarifs && toko.tarifs.length > 0 ? (
              <div className="space-y-2 max-h-48 overflow-y-auto pr-1">
                {toko.tarifs.map((tr) => {
                  const isChecked = String(selectedTarifId) === String(tr.id);
                  return (
                    <label
                      key={tr.id}
                      className={`flex items-center justify-between p-3 rounded-xl border cursor-pointer transition-all ${
                        isChecked
                          ? 'border-secondary bg-secondary/5 ring-1 ring-secondary'
                          : 'border-gray-200 hover:bg-neutral'
                      }`}
                    >
                      <div className="flex items-center gap-2.5">
                        <input
                          type="radio"
                          name="tarif"
                          value={tr.id}
                          checked={isChecked}
                          onChange={(e) => setSelectedTarifId(e.target.value)}
                          className="accent-secondary"
                        />
                        <span className="text-xs font-semibold text-gray-800">
                          {tr.jenis_layanan}
                        </span>
                      </div>
                      <span className="font-mono font-bold text-xs text-tertiary-dark">
                        Rp {Number(tr.harga).toLocaleString('id-ID')} / kg
                      </span>
                    </label>
                  );
                })}
              </div>
            ) : (
              <p className="text-xs text-rose-500">
                Toko ini belum memiliki daftar tarif layanan aktif.
              </p>
            )}
          </div>

          {/* Alamat Jemput */}
          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider">
                Alamat Penjemputan Pakaian <span className="text-rose-500">*</span>
              </label>
              <button
                type="button"
                onClick={handleDetectGPS}
                disabled={isDetectingGps}
                className="text-[11px] text-secondary hover:text-secondary-dark font-semibold flex items-center gap-1 cursor-pointer"
              >
                <span>📍</span> {isDetectingGps ? 'Mendeteksi...' : 'Pakai GPS'}
              </button>
            </div>
            <textarea
              required
              rows={3}
              value={alamatJemput}
              onChange={(e) => setAlamatJemput(e.target.value)}
              placeholder="Contoh: Jl. Tebet Barat Dalam No. 12, Pagar Putih, RT 03/RW 02..."
              className="w-full text-xs border border-gray-300 rounded-xl p-3 focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary"
            />
          </div>

          {/* Catatan Khusus */}
          <div>
            <label className="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1">
              Catatan untuk Toko / Kurir (Opsional)
            </label>
            <input
              type="text"
              value={catatan}
              onChange={(e) => setCatatan(e.target.value)}
              placeholder="Contoh: Pisahkan pakaian putih & luntur, parfum ekstra wangi"
              className="w-full text-xs border border-gray-300 rounded-xl px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-secondary focus:border-secondary"
            />
          </div>

          {/* Info Metode Bayar Cash on Delivery (COD) */}
          <div className="bg-neutral p-3.5 rounded-xl border border-gray-200/80 space-y-1.5 text-xs">
            <div className="flex items-center justify-between">
              <span className="font-semibold text-gray-600">Metode Pembayaran:</span>
              <span className="font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded-md">
                💵 Tunai / COD
              </span>
            </div>
            <p className="text-[11px] text-gray-500 leading-relaxed">
              Biaya total akan dihitung otomatis oleh sistem setelah pakaian ditimbang di toko laundry. Anda cukup membayar uang pas ke kurir saat pakaian bersih diantar kembali.
            </p>
          </div>

          {/* Action Buttons */}
          <div className="flex items-center justify-end gap-2.5 pt-2 border-t border-gray-100">
            <button
              type="button"
              onClick={onClose}
              disabled={submitting}
              className="px-4 py-2.5 rounded-xl border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors cursor-pointer"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="px-6 py-2.5 rounded-xl bg-secondary hover:bg-secondary-dark text-white text-xs font-bold transition-all shadow-md cursor-pointer disabled:opacity-50"
            >
              {submitting ? 'Memproses Order...' : 'Konfirmasi Pesanan 🧺'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
