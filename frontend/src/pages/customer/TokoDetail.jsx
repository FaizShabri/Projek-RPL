import { useState, useEffect } from 'react';
import { useParams, Link, useSearchParams } from 'react-router-dom';
import api from '../../api/client';
import RatingStars from '../../components/marketplace/RatingStars';
import CreateOrderModal from '../../components/marketplace/CreateOrderModal';

export default function TokoDetail() {
  const { id } = useParams();
  const [searchParams] = useSearchParams();

  const userLat = searchParams.get('latitude') || '';
  const userLng = searchParams.get('longitude') || '';

  const [toko, setToko] = useState(null);
  const [ratingSummary, setRatingSummary] = useState(null);
  const [reviews, setReviews] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [activeTab, setActiveTab] = useState('layanan'); // 'layanan' | 'ulasan'
  const [showOrderModal, setShowOrderModal] = useState(false);

  useEffect(() => {
    async function fetchTokoDetail() {
      try {
        setLoading(true);
        setError(null);

        const params = new URLSearchParams();
        if (userLat && userLng) {
          params.append('latitude', userLat);
          params.append('longitude', userLng);
        }

        const url = `/tokos/${id}${params.toString() ? `?${params.toString()}` : ''}`;
        const data = await api.get(url);

        setToko(data.toko);
        setRatingSummary(data.rating_summary);
        setReviews(data.reviews || []);
      } catch (err) {
        console.error('Error fetching toko detail:', err);
        setError('Gagal memuat detail toko laundry. Toko mungkin belum disetujui atau tidak ditemukan.');
      } finally {
        setLoading(false);
      }
    }

    if (id) {
      fetchTokoDetail();
    }
  }, [id, userLat, userLng]);

  if (loading) {
    return (
      <div className="min-h-screen bg-neutral py-10 px-4 sm:px-6">
        <div className="max-w-4xl mx-auto space-y-6 animate-pulse">
          <div className="h-4 bg-gray-200 rounded w-28" />
          <div className="bg-white rounded-2xl p-6 space-y-4 border border-gray-200">
            <div className="h-7 bg-gray-200 rounded w-1/2" />
            <div className="h-4 bg-gray-200 rounded w-1/3" />
            <div className="h-4 bg-gray-100 rounded w-3/4" />
          </div>
          <div className="bg-white rounded-2xl p-6 space-y-4 border border-gray-200">
            <div className="h-6 bg-gray-200 rounded w-1/4" />
            <div className="space-y-3">
              <div className="h-12 bg-gray-100 rounded-xl" />
              <div className="h-12 bg-gray-100 rounded-xl" />
              <div className="h-12 bg-gray-100 rounded-xl" />
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (error || !toko) {
    return (
      <div className="min-h-screen bg-neutral flex items-center justify-center p-4">
        <div className="bg-white rounded-2xl p-8 max-w-md w-full text-center space-y-4 border border-gray-200 shadow-sm">
          <div className="text-4xl">⚠️</div>
          <h2 className="text-lg font-bold text-gray-900">Toko Tidak Ditemukan</h2>
          <p className="text-xs text-gray-500">{error || 'Informasi toko tidak dapat ditampilkan.'}</p>
          <Link
            to="/customer"
            className="inline-block bg-primary hover:bg-primary-light text-white text-xs font-semibold px-5 py-2.5 rounded-xl transition-colors shadow-xs"
          >
            ← Kembali ke Marketplace
          </Link>
        </div>
      </div>
    );
  }

  const googleMapsUrl = toko.latitude && toko.longitude
    ? `https://www.google.com/maps/search/?api=1&query=${toko.latitude},${toko.longitude}`
    : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(toko.nama + ' ' + toko.alamat)}`;

  return (
    <div className="min-h-screen bg-neutral pb-24">
      {/* Top Navigation Bar */}
      <div className="bg-white border-b border-gray-200 sticky top-0 z-30 px-4 py-3 sm:px-6">
        <div className="max-w-4xl mx-auto flex items-center justify-between">
          <Link
            to="/customer"
            className="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 hover:text-primary transition-colors"
          >
            ← Kembali ke Marketplace
          </Link>
          <span className="text-[11px] font-bold uppercase tracking-wider text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
            ✓ Mitra Approved
          </span>
        </div>
      </div>

      <div className="max-w-4xl mx-auto px-4 sm:px-6 pt-6 space-y-6">
        {/* Toko Header Banner Card */}
        <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
            <div className="space-y-1.5">
              <div className="inline-flex items-center gap-2">
                <span className="text-2xl">🧺</span>
                <h1 className="text-xl sm:text-2xl font-extrabold text-primary">
                  {toko.nama}
                </h1>
              </div>

              <div className="flex items-center gap-2 pt-1 flex-wrap">
                <RatingStars
                  rating={ratingSummary?.average || 0}
                  size="md"
                  reviewCount={ratingSummary?.total || 0}
                />

                {toko.distance !== null && toko.distance !== undefined && (
                  <span className="bg-secondary/10 text-secondary-dark font-semibold px-2.5 py-0.5 rounded-md text-xs">
                    📍 {toko.distance} km dari Anda
                  </span>
                )}
              </div>
            </div>

            {/* Owner Info Snippet */}
            {toko.owner && (
              <div className="flex items-center gap-2.5 bg-neutral p-2.5 rounded-xl border border-gray-200/60 self-start">
                {toko.owner.avatar ? (
                  <img
                    src={toko.owner.avatar}
                    alt={toko.owner.name}
                    className="w-9 h-9 rounded-full object-cover border border-white"
                  />
                ) : (
                  <div className="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs">
                    {toko.owner.name?.charAt(0) || 'M'}
                  </div>
                )}
                <div>
                  <p className="text-[10px] text-gray-500 uppercase font-bold tracking-wider">Mitra Owner</p>
                  <p className="text-xs font-semibold text-gray-800">{toko.owner.name}</p>
                </div>
              </div>
            )}
          </div>

          {/* Address & Google Maps Link */}
          <div className="border-t border-gray-100 pt-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs text-gray-600">
            <div className="flex items-start gap-2">
              <span className="text-base shrink-0">📍</span>
              <p className="leading-relaxed">{toko.alamat}</p>
            </div>
            <a
              href={googleMapsUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-1.5 text-secondary hover:text-secondary-dark font-semibold text-xs shrink-0 self-start sm:self-auto bg-secondary/5 px-3 py-1.5 rounded-lg border border-secondary/20 transition-colors"
            >
              <span>🗺️</span> Buka Google Maps
            </a>
          </div>
        </div>

        {/* Rating Breakdown & Stats */}
        <div className="bg-white rounded-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
          <h2 className="text-base font-bold text-gray-900">Ulasan & Rating Pelanggan</h2>

          <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
            {/* Big Score Box */}
            <div className="text-center p-4 bg-amber-50/60 border border-amber-200/60 rounded-2xl space-y-1">
              <span className="text-4xl font-extrabold text-amber-500 font-mono">
                {Number(ratingSummary?.average || 0).toFixed(1)}
              </span>
              <div className="flex justify-center">
                <RatingStars
                  rating={ratingSummary?.average || 0}
                  size="md"
                  showScore={false}
                />
              </div>
              <p className="text-xs text-gray-500">
                Berdasarkan <strong>{ratingSummary?.total || 0}</strong> ulasan pelanggan
              </p>
            </div>

            {/* Star Distribution Progress Bars */}
            <div className="md:col-span-2 space-y-1.5">
              {[5, 4, 3, 2, 1].map((star) => {
                const count = ratingSummary?.breakdown?.[star] || 0;
                const total = ratingSummary?.total || 1;
                const percent = ratingSummary?.total ? Math.round((count / total) * 100) : 0;

                return (
                  <div key={star} className="flex items-center gap-3 text-xs text-gray-600">
                    <span className="w-7 font-semibold shrink-0">{star} ★</span>
                    <div className="flex-1 bg-gray-100 rounded-full h-2.5 overflow-hidden">
                      <div
                        className="bg-amber-400 h-2.5 rounded-full transition-all duration-500"
                        style={{ width: `${percent}%` }}
                      />
                    </div>
                    <span className="w-10 text-right text-gray-400 shrink-0 font-mono text-[11px]">
                      {count}
                    </span>
                  </div>
                );
              })}
            </div>
          </div>
        </div>

        {/* Tabs: Tarif Layanan vs Reviews */}
        <div className="space-y-4">
          <div className="flex border-b border-gray-200 bg-white rounded-t-2xl px-4 pt-2 gap-4">
            <button
              onClick={() => setActiveTab('layanan')}
              className={`py-3 px-2 text-xs font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-1.5 ${
                activeTab === 'layanan'
                  ? 'border-secondary text-secondary'
                  : 'border-transparent text-gray-500 hover:text-gray-800'
              }`}
            >
              <span>📋</span> Daftar Layanan & Tarif ({toko.tarifs?.length || 0})
            </button>
            <button
              onClick={() => setActiveTab('ulasan')}
              className={`py-3 px-2 text-xs font-bold border-b-2 transition-colors cursor-pointer flex items-center gap-1.5 ${
                activeTab === 'ulasan'
                  ? 'border-secondary text-secondary'
                  : 'border-transparent text-gray-500 hover:text-gray-800'
              }`}
            >
              <span>💬</span> Semua Ulasan ({reviews.length})
            </button>
          </div>

          {/* Tab 1: Tarif Layanan */}
          {activeTab === 'layanan' && (
            <div className="bg-white rounded-b-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="font-bold text-gray-900 text-sm">Pilihan Layanan Laundry</h3>
                  <p className="text-xs text-gray-500">Tarif berlaku transparan untuk pickup & delivery</p>
                </div>
                <span className="text-[11px] bg-neutral text-gray-600 font-semibold px-2.5 py-1 rounded-md border border-gray-200">
                  Pembayaran: Tunai (COD)
                </span>
              </div>

              {toko.tarifs && toko.tarifs.length > 0 ? (
                <div className="divide-y divide-gray-100 border border-gray-200/80 rounded-xl overflow-hidden">
                  {toko.tarifs.map((tr) => (
                    <div
                      key={tr.id}
                      className="p-4 flex items-center justify-between gap-4 hover:bg-neutral/40 transition-colors"
                    >
                      <div className="space-y-0.5">
                        <p className="font-semibold text-xs sm:text-sm text-gray-800">
                          {tr.jenis_layanan}
                        </p>
                        <p className="text-[11px] text-gray-400">Termasuk detergen & pewangi higienis</p>
                      </div>

                      <div className="text-right shrink-0">
                        <span className="font-mono font-bold text-sm sm:text-base text-tertiary-dark">
                          Rp {Number(tr.harga).toLocaleString('id-ID')}
                        </span>
                        <p className="text-[10px] text-gray-400">per kg / pcs</p>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8 text-xs text-gray-400 italic">
                  Belum ada daftar tarif yang ditambahkan oleh pemilik toko.
                </div>
              )}
            </div>
          )}

          {/* Tab 2: Ulasan Pelanggan */}
          {activeTab === 'ulasan' && (
            <div className="bg-white rounded-b-2xl border border-gray-200/80 p-6 shadow-xs space-y-4">
              <h3 className="font-bold text-gray-900 text-sm">Daftar Review Pelanggan</h3>

              {reviews.length > 0 ? (
                <div className="divide-y divide-gray-100 space-y-3">
                  {reviews.map((rev) => (
                    <div key={rev.id} className="pt-4 first:pt-0 space-y-2">
                      <div className="flex items-center justify-between gap-2">
                        <div className="flex items-center gap-2.5">
                          {rev.customer_avatar ? (
                            <img
                              src={rev.customer_avatar}
                              alt={rev.customer_name}
                              className="w-8 h-8 rounded-full object-cover border border-gray-200"
                            />
                          ) : (
                            <div className="w-8 h-8 rounded-full bg-secondary/10 text-secondary flex items-center justify-center font-bold text-xs">
                              {rev.customer_name?.charAt(0) || 'P'}
                            </div>
                          )}
                          <div>
                            <p className="text-xs font-bold text-gray-800">{rev.customer_name}</p>
                            <RatingStars rating={rev.rating} size="xs" showScore={false} />
                          </div>
                        </div>

                        {rev.created_at && (
                          <span className="text-[11px] text-gray-400">{rev.created_at}</span>
                        )}
                      </div>

                      {rev.komentar && (
                        <p className="text-xs text-gray-600 leading-relaxed pl-10">
                          &ldquo;{rev.komentar}&rdquo;
                        </p>
                      )}
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8 text-xs text-gray-400 italic">
                  Belum ada review untuk toko laundry ini. Jadilah yang pertama memberikan review setelah order selesai!
                </div>
              )}
            </div>
          )}
        </div>
      </div>

      {/* Mobile Sticky Order Footer */}
      <div className="fixed bottom-0 inset-x-0 bg-white/95 backdrop-blur-md border-t border-gray-200 p-4 shadow-lg z-40">
        <div className="max-w-4xl mx-auto flex items-center justify-between gap-4">
          <div className="hidden sm:block">
            <p className="font-bold text-xs text-gray-800 truncate max-w-xs">{toko.nama}</p>
            <p className="text-[11px] text-gray-500">
              {toko.tarifs?.length || 0} layanan tersedia • Bayar Tunai (COD)
            </p>
          </div>

          <button
            onClick={() => setShowOrderModal(true)}
            className="flex-1 sm:flex-none sm:min-w-[200px] bg-secondary hover:bg-secondary-dark text-white font-bold text-sm py-3 px-6 rounded-xl shadow-md transition-all text-center cursor-pointer"
          >
            🧺 Pesan Laundry Sekarang
          </button>
        </div>
      </div>

      {/* Modal Buat Order */}
      <CreateOrderModal
        toko={toko}
        isOpen={showOrderModal}
        onClose={() => setShowOrderModal(false)}
      />
    </div>
  );
}
