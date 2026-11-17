import { useState, useEffect, useCallback } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import api from '../../api/client';
import RatingStars from '../../components/marketplace/RatingStars';
import TokoSkeleton from '../../components/marketplace/TokoSkeleton';
import GooglePlacesAutocomplete from '../../components/common/GooglePlacesAutocomplete';

export default function Marketplace() {
  const [searchParams, setSearchParams] = useSearchParams();

  // Search & Filter States
  const [search, setSearch] = useState(searchParams.get('search') || '');
  const [selectedRadius, setSelectedRadius] = useState(searchParams.get('radius') || '');
  const [minRating, setMinRating] = useState(searchParams.get('min_rating') || '');
  const [sortBy, setSortBy] = useState(searchParams.get('sort') || 'default');

  // Coordinates
  const [userLat, setUserLat] = useState(
    searchParams.get('latitude') ? parseFloat(searchParams.get('latitude')) : null
  );
  const [userLng, setUserLng] = useState(
    searchParams.get('longitude') ? parseFloat(searchParams.get('longitude')) : null
  );
  const [userAddress, setUserAddress] = useState(searchParams.get('address') || '');
  const [isDetectingGps, setIsDetectingGps] = useState(false);
  const [showLocationModal, setShowLocationModal] = useState(false);

  // Data & Pagination
  const [tokos, setTokos] = useState([]);
  const [pagination, setPagination] = useState({
    currentPage: 1,
    lastPage: 1,
    total: 0,
    perPage: 9,
  });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Radius Options
  const radiusOptions = [
    { label: 'Semua Radius', value: '' },
    { label: '3 km', value: '3' },
    { label: '5 km', value: '5' },
    { label: '10 km', value: '10' },
    { label: '20 km', value: '20' },
  ];

  // Rating Filter Options
  const ratingOptions = [
    { label: 'Semua Rating', value: '' },
    { label: '★ 4.5 ke atas', value: '4.5' },
    { label: '★ 4.0 ke atas', value: '4.0' },
    { label: '★ 3.5 ke atas', value: '3.5' },
  ];

  // Fetch tokos from API
  const fetchTokos = useCallback(
    async (page = 1) => {
      try {
        setLoading(true);
        setError(null);

        const params = new URLSearchParams();
        params.append('page', page);
        params.append('per_page', 9);

        if (search.trim()) params.append('search', search.trim());
        if (minRating) params.append('min_rating', minRating);
        if (selectedRadius) params.append('radius', selectedRadius);
        if (sortBy && sortBy !== 'default') params.append('sort', sortBy);

        if (userLat !== null && userLng !== null) {
          params.append('latitude', userLat);
          params.append('longitude', userLng);
        }

        const data = await api.get(`/tokos?${params.toString()}`);

        setTokos(data.data || []);
        setPagination({
          currentPage: data.current_page || 1,
          lastPage: data.last_page || 1,
          total: data.total || 0,
          perPage: data.per_page || 9,
        });

        // Sync URL query params
        const newParams = {};
        if (search.trim()) newParams.search = search.trim();
        if (minRating) newParams.min_rating = minRating;
        if (selectedRadius) newParams.radius = selectedRadius;
        if (sortBy !== 'default') newParams.sort = sortBy;
        if (userLat !== null && userLng !== null) {
          newParams.latitude = userLat;
          newParams.longitude = userLng;
          if (userAddress) newParams.address = userAddress;
        }
        if (page > 1) newParams.page = page;
        setSearchParams(newParams, { replace: true });
      } catch (err) {
        console.error('Failed to fetch tokos:', err);
        setError('Gagal memuat katalog toko laundry. Silakan periksa koneksi Anda.');
      } finally {
        setLoading(false);
      }
    },
    [search, minRating, selectedRadius, sortBy, userLat, userLng, userAddress, setSearchParams]
  );

  useEffect(() => {
    fetchTokos(1);
  }, [fetchTokos]);

  // Handle GPS detect
  const handleDetectGPS = () => {
    if (!navigator.geolocation) {
      alert('Browser Anda tidak mendukung deteksi lokasi otomatis.');
      return;
    }

    setIsDetectingGps(true);
    navigator.geolocation.getCurrentPosition(
      (position) => {
        setUserLat(position.coords.latitude);
        setUserLng(position.coords.longitude);
        setUserAddress('Lokasi GPS Saat Ini');
        if (!selectedRadius) {
          setSelectedRadius('10');
        }
        setIsDetectingGps(false);
      },
      (err) => {
        setIsDetectingGps(false);
        alert('Gagal mendeteksi lokasi GPS: ' + err.message);
      },
      { timeout: 10000, enableHighAccuracy: true }
    );
  };

  const handleResetFilters = () => {
    setSearch('');
    setSelectedRadius('');
    setMinRating('');
    setSortBy('default');
    setUserLat(null);
    setUserLng(null);
    setUserAddress('');
  };

  const handlePlaceSelect = (place) => {
    if (place.latitude && place.longitude) {
      setUserLat(place.latitude);
      setUserLng(place.longitude);
      setUserAddress(place.alamat || 'Lokasi Terpilih');
      if (!selectedRadius) setSelectedRadius('10');
      setShowLocationModal(false);
    }
  };

  return (
    <div className="min-h-screen bg-neutral pb-16">
      {/* Top Banner / Marketplace Header */}
      <div className="bg-primary text-white pt-8 pb-12 px-4 sm:px-6 shadow-sm">
        <div className="max-w-6xl mx-auto space-y-4">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <div>
              <span className="inline-flex items-center gap-1.5 bg-white/10 px-3 py-1 rounded-full text-xs font-medium text-white/90">
                <span>🧺</span> Marketplace Pelanggan
              </span>
              <h1 className="text-2xl sm:text-3xl font-extrabold mt-2 tracking-tight">
                Cari Toko Laundry Terdekat
              </h1>
              <p className="text-xs sm:text-sm text-white/80 max-w-xl mt-1">
                Layanan cuci kiloan, express, dry clean & satuan dengan tarif transparan dan kurir jemput-antar.
              </p>
            </div>

            {/* GPS & Location Badge */}
            <div className="flex flex-col sm:flex-row items-start sm:items-center gap-2">
              {userLat !== null && userLng !== null ? (
                <div className="bg-secondary/20 border border-secondary/40 px-3 py-1.5 rounded-xl flex items-center gap-2 text-xs">
                  <span className="w-2 h-2 rounded-full bg-tertiary animate-pulse" />
                  <span className="font-medium truncate max-w-[200px]">
                    📍 {userAddress || `${userLat.toFixed(4)}, ${userLng.toFixed(4)}`}
                  </span>
                  <button
                    onClick={() => {
                      setUserLat(null);
                      setUserLng(null);
                      setUserAddress('');
                      setSelectedRadius('');
                    }}
                    className="text-white/60 hover:text-white font-bold ml-1 text-sm cursor-pointer"
                    title="Hapus Lokasi"
                  >
                    ✕
                  </button>
                </div>
              ) : (
                <button
                  onClick={handleDetectGPS}
                  disabled={isDetectingGps}
                  className="bg-secondary hover:bg-secondary-dark text-white text-xs font-semibold px-4 py-2.5 rounded-xl shadow-xs transition-colors flex items-center gap-2 cursor-pointer"
                >
                  <span>📍</span>
                  {isDetectingGps ? 'Mendeteksi GPS...' : 'Gunakan Lokasi Saya'}
                </button>
              )}

              <button
                onClick={() => setShowLocationModal(true)}
                className="bg-white/10 hover:bg-white/20 text-white text-xs font-medium px-3 py-2 rounded-xl transition-colors cursor-pointer"
              >
                Ganti Lokasi
              </button>
            </div>
          </div>

          {/* Search Form Bar */}
          <form
            onSubmit={(e) => {
              e.preventDefault();
              fetchTokos(1);
            }}
            className="pt-2 flex flex-col sm:flex-row gap-2"
          >
            <div className="relative flex-1">
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Ketik nama toko laundry atau nama jalan/area..."
                className="w-full bg-white text-gray-900 placeholder-gray-400 rounded-xl px-4 py-3 pl-10 text-sm focus:outline-none focus:ring-2 focus:ring-secondary shadow-xs"
              />
              <span className="absolute left-3.5 top-3.5 text-gray-400 text-sm select-none">
                🔍
              </span>
              {search && (
                <button
                  type="button"
                  onClick={() => setSearch('')}
                  className="absolute right-3.5 top-3 text-gray-400 hover:text-gray-600 text-sm font-bold cursor-pointer"
                >
                  ✕
                </button>
              )}
            </div>
            <button
              type="submit"
              className="bg-secondary hover:bg-secondary-dark text-white font-semibold text-sm px-6 py-3 rounded-xl shadow-xs transition-colors cursor-pointer shrink-0"
            >
              Cari Laundry
            </button>
          </form>
        </div>
      </div>

      {/* Main Content Area */}
      <div className="max-w-6xl mx-auto px-4 sm:px-6 -mt-4 space-y-6">
        {/* Filter Bar (Chips & Sort) */}
        <div className="bg-white rounded-2xl shadow-xs border border-gray-200/80 p-4 space-y-3">
          <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            {/* Radius Filters */}
            <div className="flex items-center gap-2 flex-wrap">
              <span className="text-xs font-bold text-gray-500 uppercase tracking-wider mr-1">
                Radius:
              </span>
              {radiusOptions.map((opt) => (
                <button
                  key={opt.value}
                  onClick={() => {
                    setSelectedRadius(opt.value);
                    if (opt.value && userLat === null) {
                      setShowLocationModal(true);
                    }
                  }}
                  className={`text-xs px-3 py-1.5 rounded-full font-medium transition-colors cursor-pointer ${
                    selectedRadius === opt.value
                      ? 'bg-primary text-white shadow-xs'
                      : 'bg-gray-100 hover:bg-gray-200 text-gray-700'
                  }`}
                >
                  {opt.label}
                </button>
              ))}
            </div>

            {/* Sort Selector */}
            <div className="flex items-center gap-2 self-end lg:self-auto">
              <span className="text-xs font-medium text-gray-500">Urutkan:</span>
              <select
                value={sortBy}
                onChange={(e) => setSortBy(e.target.value)}
                className="bg-gray-50 border border-gray-200 text-gray-800 text-xs rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-1 focus:ring-secondary cursor-pointer"
              >
                <option value="default">Rekomendasi</option>
                {userLat !== null && <option value="terdekat">Jarak Terdekat</option>}
                <option value="rating">Rating Tertinggi</option>
                <option value="nama">Nama Toko (A - Z)</option>
              </select>
            </div>
          </div>

          {/* Rating Filters */}
          <div className="flex items-center gap-2 flex-wrap border-t border-gray-100 pt-3">
            <span className="text-xs font-bold text-gray-500 uppercase tracking-wider mr-1">
              Rating:
            </span>
            {ratingOptions.map((opt) => (
              <button
                key={opt.value}
                onClick={() => setMinRating(opt.value)}
                className={`text-xs px-3 py-1.5 rounded-full font-medium transition-colors cursor-pointer ${
                  minRating === opt.value
                    ? 'bg-amber-500 text-white shadow-xs font-bold'
                    : 'bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200/60'
                }`}
              >
                {opt.label}
              </button>
            ))}

            {/* Active Filters Clear Button */}
            {(search || selectedRadius || minRating || userLat !== null) && (
              <button
                onClick={handleResetFilters}
                className="text-xs text-rose-600 hover:text-rose-800 font-semibold underline ml-auto cursor-pointer"
              >
                Reset Semua Filter
              </button>
            )}
          </div>
        </div>

        {/* Results Header Info */}
        <div className="flex items-center justify-between text-xs text-gray-500">
          <div>
            {!loading && (
              <p>
                Menampilkan <strong className="text-gray-900">{tokos.length}</strong> dari{' '}
                <strong className="text-gray-900">{pagination.total}</strong> toko laundry
                {selectedRadius && userLat !== null && (
                  <span> dalam radius {selectedRadius} km</span>
                )}
                {minRating && <span> dengan rating minimal {minRating}★</span>}
              </p>
            )}
          </div>
          <span className="text-xs bg-tertiary/10 text-tertiary-dark font-bold px-2.5 py-1 rounded-full">
            Mitra Terverifikasi
          </span>
        </div>

        {/* Error Alert */}
        {error && (
          <div className="bg-rose-50 border border-rose-200 text-rose-800 p-4 rounded-xl text-sm flex items-center justify-between">
            <span>⚠️ {error}</span>
            <button
              onClick={() => fetchTokos(pagination.currentPage)}
              className="bg-rose-600 text-white text-xs px-3 py-1.5 rounded-lg hover:bg-rose-700 font-medium cursor-pointer"
            >
              Coba Lagi
            </button>
          </div>
        )}

        {/* Listing Grid / Skeleton */}
        {loading ? (
          <TokoSkeleton count={6} />
        ) : tokos.length === 0 ? (
          <div className="bg-white rounded-2xl border border-gray-200/80 p-12 text-center space-y-4 max-w-md mx-auto shadow-xs">
            <div className="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center text-3xl mx-auto">
              🔍
            </div>
            <div>
              <h3 className="text-base font-bold text-gray-800">Tidak ada toko ditemukan</h3>
              <p className="text-xs text-gray-500 mt-1">
                Coba perluas jarak radius, turunkan filter rating, atau gunakan kata kunci pencarian yang lain.
              </p>
            </div>
            <button
              onClick={handleResetFilters}
              className="bg-primary hover:bg-primary-light text-white text-xs font-semibold px-5 py-2.5 rounded-xl transition-colors shadow-xs cursor-pointer"
            >
              Reset Filter & Tampilkan Semua Toko
            </button>
          </div>
        ) : (
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            {tokos.map((toko) => (
              <div
                key={toko.id}
                className="bg-white rounded-2xl border border-gray-200/80 shadow-xs hover:shadow-md transition-all flex flex-col justify-between overflow-hidden group"
              >
                {/* Toko Card Content */}
                <div className="p-5 space-y-3">
                  {/* Title & Badge */}
                  <div className="flex items-start justify-between gap-2">
                    <Link
                      to={`/customer/tokos/${toko.id}`}
                      className="font-bold text-base text-primary hover:text-secondary transition-colors line-clamp-1 group-hover:underline"
                    >
                      {toko.nama}
                    </Link>
                    <span className="shrink-0 text-[10px] uppercase font-bold tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200 px-2 py-0.5 rounded-full">
                      ✓ Approved
                    </span>
                  </div>

                  {/* Rating & Distance Badges */}
                  <div className="flex items-center justify-between text-xs">
                    <RatingStars
                      rating={toko.reviews_avg_rating || 0}
                      size="sm"
                      reviewCount={toko.reviews_count || 0}
                    />

                    {toko.distance !== null && toko.distance !== undefined && (
                      <span className="bg-secondary/10 text-secondary-dark font-semibold px-2 py-0.5 rounded-md text-[11px]">
                        📍 {toko.distance} km
                      </span>
                    )}
                  </div>

                  {/* Address */}
                  <p className="text-xs text-gray-500 line-clamp-2 leading-relaxed">
                    {toko.alamat}
                  </p>

                  {/* Services / Tarifs Preview */}
                  <div className="border-t border-gray-100 pt-3 space-y-1.5">
                    <p className="text-[11px] font-bold text-gray-500 uppercase tracking-wider">
                      Tarif Layanan:
                    </p>
                    {toko.tarifs && toko.tarifs.length > 0 ? (
                      <div className="space-y-1">
                        {toko.tarifs.slice(0, 3).map((tr) => (
                          <div
                            key={tr.id}
                            className="flex items-center justify-between text-xs text-gray-700"
                          >
                            <span className="truncate max-w-[180px]">{tr.jenis_layanan}</span>
                            <span className="font-mono font-bold text-tertiary-dark shrink-0">
                              Rp {Number(tr.harga).toLocaleString('id-ID')}
                            </span>
                          </div>
                        ))}
                        {toko.tarifs.length > 3 && (
                          <p className="text-[11px] text-secondary font-medium">
                            +{toko.tarifs.length - 3} jenis layanan lainnya
                          </p>
                        )}
                      </div>
                    ) : (
                      <p className="text-xs text-gray-400 italic">Tarif belum diatur</p>
                    )}
                  </div>
                </div>

                {/* Card Action Footer */}
                <div className="p-3.5 bg-neutral border-t border-gray-100 flex items-center gap-2">
                  <Link
                    to={`/customer/tokos/${toko.id}`}
                    className="flex-1 text-center bg-white hover:bg-gray-50 text-primary border border-gray-200 text-xs font-semibold py-2 rounded-xl transition-colors shadow-xs"
                  >
                    Detail Toko & Ulasan
                  </Link>
                  <Link
                    to={`/customer/tokos/${toko.id}`}
                    className="bg-secondary hover:bg-secondary-dark text-white text-xs font-semibold px-4 py-2 rounded-xl transition-colors shadow-xs shrink-0"
                  >
                    Pesan
                  </Link>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Pagination Controls */}
        {!loading && pagination.lastPage > 1 && (
          <div className="flex flex-wrap items-center justify-center gap-2 pt-6">
            <button
              onClick={() => fetchTokos(pagination.currentPage - 1)}
              disabled={pagination.currentPage <= 1}
              className="bg-white border border-gray-200 px-3.5 py-2 rounded-xl text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors shadow-xs cursor-pointer"
            >
              ← Sebelumnya
            </button>

            {Array.from({ length: pagination.lastPage }).map((_, idx) => {
              const p = idx + 1;
              const isActive = p === pagination.currentPage;
              return (
                <button
                  key={p}
                  onClick={() => fetchTokos(p)}
                  className={`w-9 h-9 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                    isActive
                      ? 'bg-primary text-white shadow-xs'
                      : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  {p}
                </button>
              );
            })}

            <button
              onClick={() => fetchTokos(pagination.currentPage + 1)}
              disabled={pagination.currentPage >= pagination.lastPage}
              className="bg-white border border-gray-200 px-3.5 py-2 rounded-xl text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed transition-colors shadow-xs cursor-pointer"
            >
              Selanjutnya →
            </button>
          </div>
        )}
      </div>

      {/* Location Modal Picker */}
      {showLocationModal && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-xl space-y-4">
            <div className="flex items-center justify-between border-b border-gray-100 pb-3">
              <h3 className="font-bold text-gray-900 text-base flex items-center gap-2">
                <span>📍</span> Atur Lokasi Pelanggan
              </h3>
              <button
                onClick={() => setShowLocationModal(false)}
                className="text-gray-400 hover:text-gray-600 font-bold cursor-pointer"
              >
                ✕
              </button>
            </div>

            <p className="text-xs text-gray-500">
              Pilih lokasi atau alamat Anda untuk menghitung jarak akurat ke toko laundry dan mengaktifkan filter radius.
            </p>

            <GooglePlacesAutocomplete
              onSelectPlace={handlePlaceSelect}
              initialAddress={userAddress}
              initialLat={userLat}
              initialLng={userLng}
            />

            <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
              <button
                type="button"
                onClick={() => setShowLocationModal(false)}
                className="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold px-4 py-2 rounded-xl transition-colors cursor-pointer"
              >
                Tutup
              </button>
              <button
                type="button"
                onClick={() => {
                  setShowLocationModal(false);
                  fetchTokos(1);
                }}
                className="bg-secondary hover:bg-secondary-dark text-white text-xs font-semibold px-5 py-2 rounded-xl transition-colors cursor-pointer"
              >
                Terapkan Lokasi
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
