import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import api from '../api/client';
import { useAuth } from '../contexts/AuthContext';
import RatingStars from '../components/marketplace/RatingStars';

export default function Home() {
  const navigate = useNavigate();
  const { user, activeRole } = useAuth();
  const [tokos, setTokos] = useState([]);
  const [search, setSearch] = useState('');
  const [loadingTokos, setLoadingTokos] = useState(true);
  const [health, setHealth] = useState(null);

  const fetchApprovedTokos = async (searchTerm = '') => {
    try {
      setLoadingTokos(true);
      const url = searchTerm ? `/tokos?search=${encodeURIComponent(searchTerm)}` : '/tokos';
      const data = await api.get(url);
      setTokos(data.data || []);
    } catch (err) {
      console.error('Error fetching tokos:', err);
    } finally {
      setLoadingTokos(false);
    }
  };

  useEffect(() => {
    // Health check
    api.get('/health')
      .then((data) => setHealth(data))
      .catch((err) => console.error(err));

    fetchApprovedTokos();
  }, []);

  const handleSearchSubmit = (e) => {
    e.preventDefault();
    if (search.trim()) {
      navigate(`/customer?search=${encodeURIComponent(search.trim())}`);
    } else {
      navigate('/customer');
    }
  };

  return (
    <div className="min-h-screen bg-neutral pb-16">
      {/* Hero Banner */}
      <div className="bg-primary text-white py-14 px-4 sm:px-6">
        <div className="max-w-4xl mx-auto text-center space-y-4">
          <div className="inline-flex items-center gap-2 bg-white/10 px-3.5 py-1.5 rounded-full text-xs font-medium text-white/90">
            🧺 Marketplace Laundry On-Demand #1
          </div>
          <h1 className="text-3xl sm:text-5xl font-extrabold tracking-tight">
            Pusat Laundry Indonesia
          </h1>
          <p className="text-base sm:text-lg text-white/80 max-w-2xl mx-auto">
            Temukan mitra laundry terdekat dengan tarif transparan, layanan jemput-antar oleh kurir, dan pembayaran tunai (Cash/COD).
          </p>

          {/* Search Bar Marketplace */}
          <form onSubmit={handleSearchSubmit} className="max-w-xl mx-auto pt-4 flex gap-2">
            <input
              type="text"
              placeholder="Cari toko laundry atau nama area/kota..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="flex-1 bg-white text-gray-900 placeholder-gray-400 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-secondary shadow"
            />
            <button
              type="submit"
              className="bg-secondary hover:bg-secondary-dark text-white font-semibold text-sm px-6 py-3 rounded-xl shadow transition-colors"
            >
              Cari Toko
            </button>
          </form>
        </div>
      </div>

      <div className="max-w-6xl mx-auto px-4 sm:px-6 -mt-6">
        {/* Quick Role Shortcuts */}
        <div className="bg-white rounded-2xl shadow-sm border border-gray-200 p-4 sm:p-6 mb-8 flex flex-wrap items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-secondary/10 text-secondary flex items-center justify-center font-bold text-lg">
              🏪
            </div>
            <div>
              <p className="text-sm font-bold text-gray-900">Punya Usaha Laundry?</p>
              <p className="text-xs text-gray-500">Daftarkan toko Anda menjadi mitra Pusat Laundry dan jangkau ribuan pelanggan.</p>
            </div>
          </div>
          <div className="flex gap-2">
            <Link
              to="/owner-toko"
              className="text-xs bg-primary hover:bg-primary-dark text-white font-semibold px-4 py-2.5 rounded-lg transition-colors"
            >
              Dashboard Mitra Toko
            </Link>
            {activeRole && ['platform_owner', 'admin_owner'].includes(activeRole) && (
              <Link
                to="/admin-owner"
                className="text-xs bg-amber-600 hover:bg-amber-700 text-white font-semibold px-4 py-2.5 rounded-lg transition-colors"
              >
                Panel Approval Toko
              </Link>
            )}
          </div>
        </div>

        {/* Marketplace Section */}
        <div className="space-y-6">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 className="text-xl font-bold text-gray-900">Toko Laundry Terverifikasi</h2>
              <p className="text-xs text-gray-500">Toko yang telah disetujui (Approved) dan siap menerima order</p>
            </div>
            <div className="flex items-center gap-2">
              <span className="text-xs bg-tertiary/10 text-tertiary-dark font-bold px-3 py-1 rounded-full">
                {tokos.length} Toko Aktif
              </span>
              <Link
                to="/customer"
                className="text-xs bg-secondary hover:bg-secondary-dark text-white font-semibold px-3.5 py-1.5 rounded-lg transition-colors shadow-xs"
              >
                Buka Marketplace Lengkap →
              </Link>
            </div>
          </div>

          {loadingTokos ? (
            <div className="bg-white p-12 text-center rounded-xl border border-gray-200">
              <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto mb-2" />
              <p className="text-sm text-gray-500">Memuat katalog toko...</p>
            </div>
          ) : tokos.length === 0 ? (
            <div className="bg-white p-12 text-center rounded-xl border border-gray-200 space-y-2">
              <div className="text-3xl">🔍</div>
              <p className="font-bold text-gray-800">Tidak ada toko laundry ditemukan</p>
              <p className="text-xs text-gray-500">
                {search ? 'Coba kata kunci pencarian lain.' : 'Belum ada toko yang berstatus approved saat ini.'}
              </p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
              {tokos.map((toko) => (
                <div
                  key={toko.id}
                  className="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow overflow-hidden flex flex-col justify-between"
                >
                  <div className="p-5 flex-1 space-y-3">
                    <div className="flex items-start justify-between gap-2">
                      <Link
                        to={`/customer/tokos/${toko.id}`}
                        className="font-bold text-base text-gray-900 hover:text-secondary line-clamp-1"
                      >
                        {toko.nama}
                      </Link>
                      <span className="shrink-0 text-xs bg-emerald-100 text-emerald-800 font-semibold px-2 py-0.5 rounded">
                        ✓ Approved
                      </span>
                    </div>

                    <RatingStars
                      rating={toko.reviews_avg_rating || 0}
                      size="xs"
                      reviewCount={toko.reviews_count || 0}
                    />

                    <p className="text-xs text-gray-500 line-clamp-2">📍 {toko.alamat}</p>

                    {/* Tarif Layanan Preview */}
                    <div className="border-t border-gray-100 pt-3 space-y-1.5">
                      <p className="text-xs font-semibold text-gray-600">Layanan & Tarif:</p>
                      {toko.tarifs && toko.tarifs.length > 0 ? (
                        <div className="space-y-1">
                          {toko.tarifs.slice(0, 3).map((tr) => (
                            <div key={tr.id} className="flex justify-between text-xs text-gray-700">
                              <span>{tr.jenis_layanan}</span>
                              <span className="font-mono font-semibold text-tertiary-dark">
                                Rp {tr.harga?.toLocaleString('id-ID')}
                              </span>
                            </div>
                          ))}
                          {toko.tarifs.length > 3 && (
                            <p className="text-xs text-secondary font-medium">+ {toko.tarifs.length - 3} layanan lainnya</p>
                          )}
                        </div>
                      ) : (
                        <p className="text-xs text-gray-400 italic">Tarif belum diatur</p>
                      )}
                    </div>
                  </div>

                  <div className="p-4 bg-neutral border-t border-gray-100 flex items-center justify-between">
                    <Link
                      to={`/customer/tokos/${toko.id}`}
                      className="text-xs text-primary hover:text-secondary font-semibold hover:underline"
                    >
                      Detail & Ulasan
                    </Link>
                    <Link
                      to={`/customer/tokos/${toko.id}`}
                      className="bg-secondary hover:bg-secondary-dark text-white text-xs font-semibold px-3.5 py-1.5 rounded-lg transition-colors"
                    >
                      Pesan Laundry
                    </Link>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Backend Health Check Footer */}
        {health && (
          <div className="mt-12 text-center text-xs text-gray-400">
            Backend API Health: <span className="font-mono text-tertiary-dark">{health.status}</span> ({health.message})
          </div>
        )}
      </div>
    </div>
  );
}
