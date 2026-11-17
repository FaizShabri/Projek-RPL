import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';
import RatingStars from '../../components/marketplace/RatingStars';

export default function CustomerReviews() {
  const [reviews, setReviews] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const fetchReviews = async () => {
    try {
      setLoading(true);
      setError(null);
      const res = await api.get('/customer/reviews');
      setReviews(res.data || []);
    } catch (err) {
      console.error('Failed to load customer reviews:', err);
      setError(err.message || 'Gagal memuat ulasan.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchReviews();
  }, []);

  return (
    <div className="max-w-4xl mx-auto px-4 py-8 space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-gray-200 shadow-xs">
        <div>
          <span className="text-[11px] font-bold text-secondary uppercase tracking-wider">
            Feedback & Penilaian
          </span>
          <h1 className="text-xl font-extrabold text-gray-900 mt-1">Ulasan Saya</h1>
          <p className="text-xs text-gray-500 mt-0.5">
            Daftar ulasan dan rating yang telah Anda berikan untuk layanan toko laundry dan kurir pengantar.
          </p>
        </div>
        <Link
          to="/customer/orders"
          className="bg-primary hover:bg-primary-dark text-white font-bold text-xs px-4 py-2.5 rounded-xl transition-colors shadow-xs shrink-0 text-center"
        >
          Lihat Pesanan Saya
        </Link>
      </div>

      {/* Content */}
      {loading ? (
        <div className="bg-white rounded-2xl border border-gray-200 p-12 text-center space-y-3 shadow-xs">
          <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto" />
          <p className="text-xs text-gray-500">Memuat riwayat ulasan Anda...</p>
        </div>
      ) : error ? (
        <div className="bg-rose-50 border border-rose-200 rounded-2xl p-6 text-center text-xs text-rose-800">
          <p className="font-bold">Terjadi Kesalahan</p>
          <p className="mt-1">{error}</p>
          <button
            onClick={fetchReviews}
            className="mt-3 bg-rose-600 text-white font-bold px-4 py-1.5 rounded-lg hover:bg-rose-700 cursor-pointer"
          >
            Coba Lagi
          </button>
        </div>
      ) : reviews.length === 0 ? (
        <div className="bg-white rounded-2xl border border-gray-200 p-12 text-center space-y-3 shadow-xs">
          <div className="text-4xl">⭐</div>
          <h3 className="font-bold text-sm text-gray-800">Belum Ada Ulasan</h3>
          <p className="text-xs text-gray-500 max-w-sm mx-auto">
            Anda belum pernah memberikan ulasan. Setelah pesanan selesai, Anda dapat memberikan penilaian untuk toko dan kurir.
          </p>
          <Link
            to="/customer/orders"
            className="inline-block bg-secondary text-white font-bold text-xs px-4 py-2 rounded-xl hover:bg-secondary-dark transition-colors cursor-pointer"
          >
            Buka Riwayat Pesanan
          </Link>
        </div>
      ) : (
        <div className="space-y-4">
          {reviews.map((rev) => (
            <div
              key={rev.id}
              className="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs space-y-3 hover:border-gray-300 transition-all"
            >
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 pb-3">
                <div className="flex items-center gap-2.5">
                  <span className="text-2xl">
                    {rev.toko_id ? '🏪' : '🛵'}
                  </span>
                  <div>
                    <h3 className="font-bold text-sm text-gray-900">
                      {rev.toko ? rev.toko.nama : rev.kurir ? `Kurir: ${rev.kurir.name}` : 'Ulasan Layanan'}
                    </h3>
                    <p className="text-[11px] text-gray-400">
                      Order #{rev.order?.nomor_order || `ORD-${rev.order?.id}`}
                      {rev.toko?.alamat && ` • ${rev.toko.alamat}`}
                    </p>
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <RatingStars rating={rev.rating} size="sm" />
                  <span className="font-bold text-xs font-mono text-amber-600">
                    {rev.rating}.0
                  </span>
                </div>
              </div>

              {rev.komentar ? (
                <div className="bg-neutral/80 rounded-xl p-3 border border-gray-100 text-xs text-gray-700 leading-relaxed">
                  "{rev.komentar}"
                </div>
              ) : (
                <p className="text-xs text-gray-400 italic">Tanpa komentar tertulis.</p>
              )}

              <div className="flex items-center justify-between text-[11px] text-gray-400 pt-1">
                <span className="inline-flex items-center gap-1 font-semibold text-gray-500">
                  {rev.toko_id ? '🏷️ Ulasan Toko Laundry' : '🛵 Ulasan Pengantaran Kurir'}
                </span>
                <span>
                  {new Date(rev.created_at).toLocaleDateString('id-ID', {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                  })}
                </span>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
