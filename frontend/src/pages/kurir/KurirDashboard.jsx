import { useState, useEffect } from 'react';
import api from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';
import { useKurirGPS } from '../../hooks/useKurirGPS';
import KurirGPSStatus from '../../components/tracking/KurirGPSStatus';
import DashboardLayout from '../../components/dashboard/DashboardLayout';
import StatCard from '../../components/dashboard/StatCard';
import StatusBadge from '../../components/dashboard/StatusBadge';

export default function KurirDashboard() {
  const { user } = useAuth();
  const [activeTab, setActiveTab] = useState('active'); // 'active' | 'available' | 'history' | 'reviews'
  const [availableTasks, setAvailableTasks] = useState([]);
  const [myTasks, setMyTasks] = useState([]);
  const [reviewsData, setReviewsData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);

  // Active tasks (in progress) vs completed history
  const activeOrders = myTasks.filter((t) => !['selesai', 'dibatalkan'].includes(t.status));
  const historyOrders = myTasks.filter((t) => ['selesai', 'dibatalkan'].includes(t.status));

  // Real-time GPS Tracking hook: auto-shares GPS during 'dijemput' or 'diantar' status
  const gps = useKurirGPS(myTasks);

  const fetchTasks = async () => {
    try {
      setLoading(true);
      const [availRes, myRes] = await Promise.all([
        api.get('/kurir/tasks/available'),
        api.get('/kurir/tasks/my'),
      ]);
      setAvailableTasks(availRes.data || []);
      setMyTasks(myRes.data || []);
    } catch (err) {
      console.error('Failed to load kurir tasks:', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchReviews = async () => {
    try {
      const res = await api.get('/kurir/reviews');
      setReviewsData(res);
    } catch (err) {
      console.error('Failed to load kurir reviews:', err);
    }
  };

  useEffect(() => {
    fetchTasks();
  }, []);

  useEffect(() => {
    if (activeTab === 'reviews') {
      fetchReviews();
    }
  }, [activeTab]);

  const handleAcceptTask = async (orderId) => {
    try {
      setActionLoading(true);
      await api.post(`/kurir/tasks/${orderId}/accept`);
      alert('Tugas berhasil diambil! Segera jemput cucian ke alamat pelanggan.');
      setActiveTab('active');
      await fetchTasks();
    } catch (err) {
      alert('Gagal mengambil tugas: ' + err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handleUpdateStatus = async (orderId, targetStatus, keterangan) => {
    try {
      setActionLoading(true);
      await api.post(`/kurir/tasks/${orderId}/status`, {
        status: targetStatus,
        keterangan: keterangan || null,
      });
      alert('Status berhasil diperbarui!');
      await fetchTasks();
    } catch (err) {
      alert('Gagal update status: ' + err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const navItems = [
    { label: 'Tugas Aktif', icon: '⚡', path: '#' },
    { label: 'Tugas Tersedia', icon: '📋', path: '#' },
    { label: 'Riwayat Pengantaran', icon: '📦', path: '#' },
    { label: 'Rating & Ulasan', icon: '⭐', path: '#' },
  ];

  return (
    <DashboardLayout
      requiredRole="kurir"
      title="Dashboard Mitra Kurir"
      subtitle="Kelola penjemputan dan pengantaran laundry, lacak GPS real-time, dan pantau rating pelanggan."
      badgeIcon="🛵"
      navItems={navItems}
    >
      <div className="space-y-6">
        {/* GPS Tracking Live Indicator Bar */}
        <KurirGPSStatus
          isTracking={gps.isTracking}
          lastPing={gps.lastPing}
          error={gps.error}
          gpsPermission={gps.gpsPermission}
          trackingOrderCount={gps.trackingOrderCount}
        />

        {/* Quick Stat Cards */}
        <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
          <StatCard
            title="Tugas Aktif"
            value={activeOrders.length}
            icon="⚡"
            color="secondary"
            subtitle="Sedang berjalan"
          />
          <StatCard
            title="Tugas Tersedia"
            value={availableTasks.length}
            icon="📋"
            color="primary"
            subtitle="Menunggu kurir"
          />
          <StatCard
            title="Selesai Diantar"
            value={historyOrders.filter((o) => o.status === 'selesai').length}
            icon="✅"
            color="tertiary"
            subtitle="Pengantaran tuntas"
          />
          <StatCard
            title="Rating Kurir"
            value={reviewsData ? `★ ${reviewsData.average_rating}` : '★ 5.0'}
            icon="⭐"
            color="accent"
            subtitle={`${reviewsData?.total_reviews || 0} ulasan customer`}
          />
        </div>

        {/* Tab Navigation Buttons */}
        <div className="flex border-b border-gray-200 bg-white rounded-2xl p-1.5 shadow-xs overflow-x-auto gap-1">
          <button
            onClick={() => setActiveTab('active')}
            className={`px-4 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5 ${
              activeTab === 'active'
                ? 'bg-secondary text-white shadow-xs'
                : 'text-gray-600 hover:text-secondary hover:bg-gray-100'
            }`}
          >
            <span>⚡</span>
            <span>Tugas Aktif ({activeOrders.length})</span>
          </button>
          <button
            onClick={() => setActiveTab('available')}
            className={`px-4 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5 ${
              activeTab === 'available'
                ? 'bg-secondary text-white shadow-xs'
                : 'text-gray-600 hover:text-secondary hover:bg-gray-100'
            }`}
          >
            <span>📋</span>
            <span>Tugas Tersedia ({availableTasks.length})</span>
          </button>
          <button
            onClick={() => setActiveTab('history')}
            className={`px-4 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5 ${
              activeTab === 'history'
                ? 'bg-secondary text-white shadow-xs'
                : 'text-gray-600 hover:text-secondary hover:bg-gray-100'
            }`}
          >
            <span>📦</span>
            <span>Riwayat Pengantaran ({historyOrders.length})</span>
          </button>
          <button
            onClick={() => setActiveTab('reviews')}
            className={`px-4 py-2.5 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5 ${
              activeTab === 'reviews'
                ? 'bg-secondary text-white shadow-xs'
                : 'text-gray-600 hover:text-secondary hover:bg-gray-100'
            }`}
          >
            <span>⭐</span>
            <span>Rating & Ulasan</span>
          </button>
        </div>

        {/* Tab Content */}
        {loading ? (
          <div className="bg-white rounded-2xl border border-gray-200 p-12 text-center space-y-2">
            <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto" />
            <p className="text-xs text-gray-500">Memuat data operasional kurir...</p>
          </div>
        ) : (
          <>
            {/* TAB 1: TUGAS AKTIF */}
            {activeTab === 'active' && (
              <div className="space-y-4">
                {activeOrders.length === 0 ? (
                  <div className="bg-white rounded-2xl border border-gray-200 p-12 text-center space-y-3">
                    <div className="text-4xl">🛵</div>
                    <h3 className="font-bold text-sm text-gray-800">Tidak ada tugas aktif saat ini</h3>
                    <p className="text-xs text-gray-500 max-w-sm mx-auto">
                      Anda sedang tidak memiliki tugas penjemputan atau pengantaran yang sedang berjalan.
                    </p>
                    <button
                      onClick={() => setActiveTab('available')}
                      className="bg-secondary text-white text-xs font-bold px-4 py-2 rounded-xl cursor-pointer hover:bg-secondary-dark transition-colors inline-block"
                    >
                      Lihat Tugas Tersedia ({availableTasks.length})
                    </button>
                  </div>
                ) : (
                  activeOrders.map((task) => (
                    <div
                      key={task.id}
                      className="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs space-y-4"
                    >
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 pb-3">
                        <div>
                          <div className="flex items-center gap-2">
                            <span className="font-bold text-sm text-gray-900">
                              #ORD-{task.id} • {task.customer?.name}
                            </span>
                            <StatusBadge status={task.status} />
                          </div>
                          <p className="text-xs text-gray-500 mt-0.5">
                            Toko: <strong>{task.toko?.nama}</strong> • Layanan:{' '}
                            {task.tarif?.jenis_layanan || 'Reguler'}
                          </p>
                        </div>

                        {task.total_biaya && (
                          <div className="sm:text-right">
                            <span className="text-[10px] uppercase font-bold text-emerald-800 bg-emerald-100 px-2 py-0.5 rounded">
                              Tagihan COD
                            </span>
                            <p className="font-mono font-bold text-base text-emerald-900 mt-0.5">
                              Rp {Number(task.total_biaya).toLocaleString('id-ID')}
                            </p>
                          </div>
                        )}
                      </div>

                      {/* Detail Alamat */}
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div className="p-3 bg-neutral rounded-xl border border-gray-100">
                          <p className="text-[10px] font-bold text-gray-400 uppercase">📍 Alamat Pelanggan:</p>
                          <p className="font-semibold text-gray-800 mt-1">{task.customer?.name}</p>
                          <p className="text-gray-600 mt-0.5">{task.alamat_jemput}</p>
                        </div>
                        <div className="p-3 bg-neutral rounded-xl border border-gray-100">
                          <p className="text-[10px] font-bold text-gray-400 uppercase">🏪 Toko Laundry:</p>
                          <p className="font-semibold text-gray-800 mt-1">{task.toko?.nama}</p>
                          <p className="text-gray-600 mt-0.5">{task.toko?.alamat}</p>
                        </div>
                      </div>

                      {task.catatan && (
                        <div className="bg-amber-50 p-3 rounded-xl border border-amber-200/60 text-xs text-amber-900">
                          <strong>Catatan Customer:</strong> {task.catatan}
                        </div>
                      )}

                      {/* Workflow Step Actions */}
                      <div className="border-t border-gray-100 pt-3 flex flex-wrap items-center gap-2">
                        {task.status === 'menunggu_kurir' && (
                          <button
                            onClick={() =>
                              handleUpdateStatus(
                                task.id,
                                'dijemput',
                                'Kurir telah mengambil pakaian dari customer.'
                              )
                            }
                            disabled={actionLoading}
                            className="bg-primary hover:bg-primary-dark text-white font-bold text-xs px-4 py-2 rounded-xl transition-colors cursor-pointer"
                          >
                            🧺 Konfirmasi Pakaian Dijemput
                          </button>
                        )}

                        {task.status === 'dijemput' && (
                          <button
                            onClick={() =>
                              handleUpdateStatus(
                                task.id,
                                'di_toko',
                                'Kurir menyerahkan pakaian ke toko laundry.'
                              )
                            }
                            disabled={actionLoading}
                            className="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-colors cursor-pointer"
                          >
                            🏪 Konfirmasi Tiba & Serahkan ke Toko
                          </button>
                        )}

                        {task.status === 'siap_diantar' && (
                          <button
                            onClick={() =>
                              handleUpdateStatus(
                                task.id,
                                'diantar',
                                'Kurir mengambil pakaian bersih dan mulai mengantar ke customer.'
                              )
                            }
                            disabled={actionLoading}
                            className="bg-secondary hover:bg-secondary-dark text-white font-bold text-xs px-4 py-2 rounded-xl transition-colors cursor-pointer"
                          >
                            🛵 Mulai Antar ke Pelanggan
                          </button>
                        )}

                        {task.status === 'diantar' && (
                          <button
                            onClick={() =>
                              handleUpdateStatus(
                                task.id,
                                'cod_selesai',
                                'Pelanggan menerima pakaian dan membayar tunai COD lunas.'
                              )
                            }
                            disabled={actionLoading}
                            className="bg-tertiary hover:bg-tertiary-dark text-white font-bold text-xs px-5 py-2 rounded-xl transition-colors shadow-xs cursor-pointer"
                          >
                            💵 Terima Uang COD ({task.total_biaya ? `Rp ${Number(task.total_biaya).toLocaleString('id-ID')}` : 'Cash'}) & Selesaikan
                          </button>
                        )}

                        {task.status === 'cod_selesai' && (
                          <button
                            onClick={() =>
                              handleUpdateStatus(task.id, 'selesai', 'Pesanan selesai tuntas.')
                            }
                            disabled={actionLoading}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs px-4 py-2 rounded-xl transition-colors cursor-pointer"
                          >
                            ✓ Tutup Pesanan (Selesai)
                          </button>
                        )}

                        {['di_toko', 'menunggu_pembayaran_info', 'diproses'].includes(task.status) && (
                          <span className="text-xs text-gray-500 italic flex items-center gap-1.5">
                            <span>⏳</span>
                            <span>Menunggu proses penimbangan & pencucian oleh toko laundry...</span>
                          </span>
                        )}
                      </div>
                    </div>
                  ))
                )}
              </div>
            )}

            {/* TAB 2: TUGAS TERSEDIA */}
            {activeTab === 'available' && (
              <div className="space-y-4">
                {availableTasks.length === 0 ? (
                  <div className="bg-white rounded-2xl border border-gray-200 p-12 text-center space-y-2">
                    <div className="text-3xl">☕</div>
                    <h3 className="font-bold text-sm text-gray-800">Tidak ada tugas baru saat ini</h3>
                    <p className="text-xs text-gray-500">
                      Semua order penjemputan & pengantaran sudah diambil oleh mitra kurir. Coba refresh beberapa saat lagi.
                    </p>
                    <button
                      onClick={fetchTasks}
                      className="bg-primary text-white text-xs font-semibold px-4 py-2 rounded-xl mt-2 cursor-pointer"
                    >
                      Refresh Antrean
                    </button>
                  </div>
                ) : (
                  availableTasks.map((task) => (
                    <div
                      key={task.id}
                      className="bg-white rounded-2xl border border-gray-200/90 p-5 shadow-xs hover:shadow-md transition-all space-y-3"
                    >
                      <div className="flex items-start justify-between gap-2 border-b border-gray-100 pb-3">
                        <div>
                          <span className="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-full bg-blue-100 text-blue-800">
                            {task.status === 'siap_diantar' ? '🛵 Antar Pakaian Bersih' : '🧺 Jemput Pakaian Kotor'}
                          </span>
                          <h4 className="font-bold text-sm text-gray-900 mt-1">
                            Pesanan #ORD-{task.id} • {task.tarif?.jenis_layanan || 'Reguler'}
                          </h4>
                        </div>
                        <button
                          onClick={() => handleAcceptTask(task.id)}
                          disabled={actionLoading}
                          className="bg-secondary hover:bg-secondary-dark text-white font-bold text-xs px-4 py-2 rounded-xl shadow-xs transition-colors cursor-pointer"
                        >
                          Terima Tugas
                        </button>
                      </div>

                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div className="space-y-0.5">
                          <p className="text-[10px] font-bold text-gray-400 uppercase">Lokasi Customer:</p>
                          <p className="font-semibold text-gray-800">{task.customer?.name}</p>
                          <p className="text-gray-600 line-clamp-2">📍 {task.alamat_jemput}</p>
                        </div>
                        <div className="space-y-0.5">
                          <p className="text-[10px] font-bold text-gray-400 uppercase">Toko Tujuan:</p>
                          <p className="font-semibold text-gray-800">{task.toko?.nama}</p>
                          <p className="text-gray-600 line-clamp-2">🏪 {task.toko?.alamat}</p>
                        </div>
                      </div>

                      {task.catatan && (
                        <div className="bg-amber-50 p-2.5 rounded-xl border border-amber-200/60 text-xs text-amber-900">
                          <strong>Catatan:</strong> {task.catatan}
                        </div>
                      )}
                    </div>
                  ))
                )}
              </div>
            )}

            {/* TAB 3: RIWAYAT PENGANTARAN */}
            {activeTab === 'history' && (
              <div className="space-y-4">
                {historyOrders.length === 0 ? (
                  <div className="bg-white rounded-2xl border border-gray-200 p-12 text-center space-y-2">
                    <div className="text-3xl">📦</div>
                    <h3 className="font-bold text-sm text-gray-800">Belum ada riwayat pengantaran selesai</h3>
                    <p className="text-xs text-gray-500">
                      Pesanan yang telah Anda selesaikan akan tercatat di sini.
                    </p>
                  </div>
                ) : (
                  <div className="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-xs">
                    <div className="p-4 border-b border-gray-100 flex items-center justify-between">
                      <h3 className="font-bold text-sm text-gray-900">Riwayat Pengantaran Selesai</h3>
                      <span className="text-xs text-gray-500 font-mono">Total: {historyOrders.length}</span>
                    </div>
                    <div className="divide-y divide-gray-100">
                      {historyOrders.map((order) => (
                        <div key={order.id} className="p-4 hover:bg-neutral/50 transition-colors flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                          <div className="space-y-1">
                            <div className="flex items-center gap-2">
                              <span className="font-bold text-gray-900">#ORD-{order.id}</span>
                              <StatusBadge status={order.status} />
                              <span className="text-gray-400">•</span>
                              <span className="text-gray-500 font-semibold">{order.customer?.name}</span>
                            </div>
                            <p className="text-gray-600">
                              Toko: <strong>{order.toko?.nama}</strong> | Alamat: {order.alamat_jemput}
                            </p>
                          </div>
                          <div className="sm:text-right shrink-0">
                            <p className="font-mono font-bold text-emerald-800">
                              {order.total_biaya ? `Rp ${Number(order.total_biaya).toLocaleString('id-ID')}` : '-'}
                            </p>
                            <span className="text-[10px] text-gray-400">
                              {new Date(order.created_at).toLocaleDateString('id-ID', {
                                day: 'numeric',
                                month: 'short',
                                year: 'numeric',
                              })}
                            </span>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}

            {/* TAB 4: RATING & ULASAN CUSTOMER */}
            {activeTab === 'reviews' && (
              <div className="space-y-6">
                {/* Summary Card */}
                <div className="bg-white rounded-2xl border border-gray-200 p-6 shadow-xs">
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                    <div className="text-center md:border-r border-gray-100">
                      <p className="text-xs text-gray-500 font-bold uppercase tracking-wider">Rating Rata-Rata</p>
                      <div className="text-4xl font-extrabold text-amber-500 mt-2 flex items-center justify-center gap-1 font-mono">
                        <span>★</span>
                        <span>{reviewsData?.average_rating || '5.0'}</span>
                      </div>
                      <p className="text-xs text-gray-400 mt-1">
                        Dari total {reviewsData?.total_reviews || 0} ulasan
                      </p>
                    </div>

                    <div className="md:col-span-2 space-y-1.5">
                      {[5, 4, 3, 2, 1].map((stars) => {
                        const count = reviewsData?.rating_breakdown?.[stars] || 0;
                        const total = reviewsData?.total_reviews || 1;
                        const pct = Math.round((count / (reviewsData?.total_reviews || 1)) * 100);
                        return (
                          <div key={stars} className="flex items-center gap-2 text-xs">
                            <span className="w-12 font-semibold text-gray-600 flex items-center gap-0.5">
                              {stars} <span>★</span>
                            </span>
                            <div className="flex-1 bg-gray-100 rounded-full h-2 overflow-hidden">
                              <div
                                className="bg-amber-400 h-2 rounded-full transition-all"
                                style={{ width: `${reviewsData?.total_reviews ? pct : 0}%` }}
                              />
                            </div>
                            <span className="w-10 text-right text-gray-400 font-mono text-[11px]">
                              {count}
                            </span>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                </div>

                {/* Reviews List */}
                <div className="bg-white rounded-2xl border border-gray-200 overflow-hidden shadow-xs">
                  <div className="p-4 border-b border-gray-100">
                    <h3 className="font-bold text-sm text-gray-900">Ulasan & Feedback dari Pelanggan</h3>
                  </div>

                  {!reviewsData || reviewsData.reviews?.data?.length === 0 ? (
                    <div className="p-12 text-center space-y-2">
                      <div className="text-3xl">⭐</div>
                      <h4 className="font-bold text-sm text-gray-800">Belum ada ulasan yang diterima</h4>
                      <p className="text-xs text-gray-500">
                        Ulasan dari pelanggan setelah order selesai akan otomatis tampil di sini.
                      </p>
                    </div>
                  ) : (
                    <div className="divide-y divide-gray-100">
                      {reviewsData.reviews.data.map((rev) => (
                        <div key={rev.id} className="p-5 space-y-2">
                          <div className="flex items-start justify-between gap-2">
                            <div className="flex items-center gap-2.5">
                              <div className="w-9 h-9 rounded-full bg-primary/10 text-primary font-bold text-xs flex items-center justify-center">
                                {rev.order?.customer?.name?.charAt(0) || 'C'}
                              </div>
                              <div>
                                <p className="font-bold text-xs text-gray-900">
                                  {rev.order?.customer?.name || 'Pelanggan'}
                                </p>
                                <p className="text-[10px] text-gray-400">
                                  Order #{rev.order?.nomor_order || `ORD-${rev.order?.id}`}
                                </p>
                              </div>
                            </div>
                            <div className="flex items-center gap-1 text-amber-500 font-bold text-sm">
                              {'★'.repeat(rev.rating)}
                              <span className="text-gray-300 font-normal">
                                {'★'.repeat(5 - rev.rating)}
                              </span>
                            </div>
                          </div>
                          {rev.komentar && (
                            <p className="text-xs text-gray-700 bg-neutral/60 p-3 rounded-xl border border-gray-100">
                              "{rev.komentar}"
                            </p>
                          )}
                          <p className="text-[10px] text-gray-400 text-right">
                            {new Date(rev.created_at).toLocaleDateString('id-ID', {
                              day: 'numeric',
                              month: 'long',
                              year: 'numeric',
                            })}
                          </p>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </DashboardLayout>
  );
}
