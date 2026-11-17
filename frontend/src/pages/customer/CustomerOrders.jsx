import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';
import RatingStars from '../../components/marketplace/RatingStars';
import KurirTrackingMap from '../../components/tracking/KurirTrackingMap';

const STATUS_CONFIG = {
  menunggu_konfirmasi: {
    label: 'Menunggu Konfirmasi',
    color: 'bg-amber-100 text-amber-800 border-amber-300',
    step: 1,
  },
  menunggu_kurir: {
    label: 'Menunggu Kurir Jemput',
    color: 'bg-blue-100 text-blue-800 border-blue-300',
    step: 2,
  },
  dijemput: {
    label: 'Pakaian Dijemput Kurir',
    color: 'bg-indigo-100 text-indigo-800 border-indigo-300',
    step: 3,
  },
  di_toko: {
    label: 'Tiba di Toko (Proses Timbang)',
    color: 'bg-purple-100 text-purple-800 border-purple-300',
    step: 4,
  },
  menunggu_pembayaran_info: {
    label: 'Sudah Ditimbang (Info Tagihan)',
    color: 'bg-teal-100 text-teal-800 border-teal-300',
    step: 5,
  },
  diproses: {
    label: 'Sedang Dicuci & Disetrika',
    color: 'bg-sky-100 text-sky-800 border-sky-300',
    step: 6,
  },
  siap_diantar: {
    label: 'Selesai Cuci (Siap Diantar)',
    color: 'bg-cyan-100 text-cyan-800 border-cyan-300',
    step: 7,
  },
  diantar: {
    label: 'Kurir Sedang Mengantar',
    color: 'bg-yellow-100 text-yellow-800 border-yellow-300',
    step: 8,
  },
  cod_selesai: {
    label: 'COD Selesai & Diterima',
    color: 'bg-emerald-100 text-emerald-800 border-emerald-300',
    step: 9,
  },
  selesai: {
    label: 'Pesanan Selesai',
    color: 'bg-emerald-100 text-emerald-800 border-emerald-300',
    step: 9,
  },
  dibatalkan: {
    label: 'Pesanan Dibatalkan',
    color: 'bg-rose-100 text-rose-800 border-rose-300',
    step: 0,
  },
};

const STEP_LABELS = [
  'Dipesan',
  'Menunggu Kurir',
  'Dijemput',
  'Di Toko',
  'Ditimbang',
  'Dicuci',
  'Siap Diantar',
  'Diantar',
  'Selesai',
];

export default function CustomerOrders() {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [selectedOrder, setSelectedOrder] = useState(null);
  const [showTimelineModal, setShowTimelineModal] = useState(false);
  const [showReviewModal, setShowReviewModal] = useState(false);
  const [showCancelModal, setShowCancelModal] = useState(false);

  // Review states
  const [tokoRating, setTokoRating] = useState(5);
  const [tokoKomentar, setTokoKomentar] = useState('');
  const [kurirRating, setKurirRating] = useState(5);
  const [kurirKomentar, setKurirKomentar] = useState('');
  const [submittingReview, setSubmittingReview] = useState(false);

  // Cancel states
  const [cancelReason, setCancelReason] = useState('');
  const [cancelling, setCancelling] = useState(false);

  // Dispute states
  const [showDisputeModal, setShowDisputeModal] = useState(false);
  const [disputeOrder, setDisputeOrder] = useState(null);
  const [disputeReason, setDisputeReason] = useState('berat_tidak_sesuai');
  const [disputeDescription, setDisputeDescription] = useState('');
  const [submittingDispute, setSubmittingDispute] = useState(false);

  // Filter tab: 'all' | 'active' | 'completed'
  const [filterTab, setFilterTab] = useState('all');

  const fetchOrders = async () => {
    try {
      setLoading(true);
      const res = await api.get('/customer/orders');
      setOrders(res.data || []);
    } catch (err) {
      console.error('Failed to load customer orders:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchOrders();
  }, []);

  const handleOpenTimeline = async (orderId) => {
    try {
      const res = await api.get(`/customer/orders/${orderId}`);
      setSelectedOrder(res.order);
      setShowTimelineModal(true);
    } catch (err) {
      alert('Gagal memuat timeline: ' + err.message);
    }
  };

  const handleOpenReview = (order) => {
    setSelectedOrder(order);
    setTokoRating(5);
    setTokoKomentar('');
    setKurirRating(5);
    setKurirKomentar('');
    setShowReviewModal(true);
  };

  const handleSubmitReview = async (e) => {
    e.preventDefault();
    if (!selectedOrder) return;

    try {
      setSubmittingReview(true);
      await api.post(`/customer/orders/${selectedOrder.id}/review`, {
        toko_rating: tokoRating,
        toko_komentar: tokoKomentar.trim() || null,
        kurir_rating: kurirRating,
        kurir_komentar: kurirKomentar.trim() || null,
      });

      alert('Terima kasih! Ulasan Anda untuk toko dan kurir berhasil dikirim.');
      setShowReviewModal(false);
      fetchOrders();
    } catch (err) {
      alert('Gagal mengirim ulasan: ' + err.message);
    } finally {
      setSubmittingReview(false);
    }
  };

  const handleCancelOrder = async () => {
    if (!selectedOrder) return;
    try {
      setCancelling(true);
      await api.post(`/customer/orders/${selectedOrder.id}/cancel`, {
        alasan: cancelReason.trim() || 'Dibatalkan oleh customer.',
      });
      alert('Pesanan berhasil dibatalkan.');
      setShowCancelModal(false);
      fetchOrders();
    } catch (err) {
      alert('Gagal membatalkan pesanan: ' + err.message);
    } finally {
      setCancelling(false);
    }
  };

  const handleOpenDispute = (order) => {
    setDisputeOrder(order);
    setDisputeReason('berat_tidak_sesuai');
    setDisputeDescription('');
    setShowDisputeModal(true);
  };

  const handleSubmitDispute = async (e) => {
    e.preventDefault();
    if (!disputeOrder) return;
    if (!disputeDescription || disputeDescription.trim().length < 10) {
      alert('Deskripsi komplain / dispute minimal 10 karakter.');
      return;
    }
    try {
      setSubmittingDispute(true);
      await api.post(`/orders/${disputeOrder.id}/dispute`, {
        alasan: disputeReason,
        deskripsi: disputeDescription.trim(),
      });
      alert('Dispute / komplain Anda berhasil diajukan dan sedang ditinjau oleh Admin Platform.');
      setShowDisputeModal(false);
      setDisputeOrder(null);
      setDisputeDescription('');
      fetchOrders();
    } catch (err) {
      alert('Gagal mengajukan dispute: ' + err.message);
    } finally {
      setSubmittingDispute(false);
    }
  };

  const filteredOrders = orders.filter((o) => {
    if (filterTab === 'active') {
      return !['selesai', 'dibatalkan'].includes(o.status);
    }
    if (filterTab === 'completed') {
      return o.status === 'selesai';
    }
    if (filterTab === 'cancelled') {
      return o.status === 'dibatalkan';
    }
    return true;
  });

  return (
    <div className="min-h-screen bg-neutral pb-20">
      {/* Subnav Customer */}
      <div className="bg-white border-b border-gray-200 px-4 py-3 sticky top-0 z-20">
        <div className="max-w-4xl mx-auto flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Link
              to="/customer"
              className="text-xs font-semibold text-gray-500 hover:text-primary transition-colors flex items-center gap-1"
            >
              <span>🧺</span> Marketplace
            </Link>
            <span className="text-gray-300">/</span>
            <span className="text-xs font-bold text-primary">Pesanan Saya</span>
          </div>

          <Link
            to="/customer"
            className="text-xs bg-secondary hover:bg-secondary-dark text-white font-semibold px-3 py-1.5 rounded-lg transition-colors"
          >
            + Pesan Laundry Baru
          </Link>
        </div>
      </div>

      <div className="max-w-4xl mx-auto px-4 sm:px-6 pt-6 space-y-5">
        {/* Title & Filter Tabs */}
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h1 className="text-2xl font-extrabold text-primary tracking-tight">
              Pesanan Laundry Saya
            </h1>
            <p className="text-xs text-gray-500 mt-0.5">
              Pantau status penjemputan, penimbangan biaya COD, proses cuci, dan serah terima
            </p>
          </div>

          {/* Filter Pills */}
          <div className="flex items-center gap-1 bg-white p-1 rounded-xl border border-gray-200 self-start sm:self-auto text-xs">
            <button
              onClick={() => setFilterTab('all')}
              className={`px-3 py-1.5 rounded-lg font-semibold transition-colors cursor-pointer ${
                filterTab === 'all' ? 'bg-primary text-white shadow-xs' : 'text-gray-600 hover:text-gray-900'
              }`}
            >
              Semua ({orders.length})
            </button>
            <button
              onClick={() => setFilterTab('active')}
              className={`px-3 py-1.5 rounded-lg font-semibold transition-colors cursor-pointer ${
                filterTab === 'active' ? 'bg-primary text-white shadow-xs' : 'text-gray-600 hover:text-gray-900'
              }`}
            >
              Aktif
            </button>
            <button
              onClick={() => setFilterTab('completed')}
              className={`px-3 py-1.5 rounded-lg font-semibold transition-colors cursor-pointer ${
                filterTab === 'completed' ? 'bg-primary text-white shadow-xs' : 'text-gray-600 hover:text-gray-900'
              }`}
            >
              Selesai
            </button>
          </div>
        </div>

        {/* Orders Listing */}
        {loading ? (
          <div className="bg-white rounded-2xl border border-gray-200 p-12 text-center space-y-3">
            <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto" />
            <p className="text-xs text-gray-500">Memuat riwayat pesanan...</p>
          </div>
        ) : filteredOrders.length === 0 ? (
          <div className="bg-white rounded-2xl border border-gray-200 p-12 text-center space-y-3">
            <div className="text-4xl">🧺</div>
            <h3 className="text-base font-bold text-gray-800">Belum Ada Pesanan</h3>
            <p className="text-xs text-gray-500 max-w-sm mx-auto">
              Anda belum memiliki pesanan laundry. Cari toko laundry terdekat dan pesan sekarang!
            </p>
            <Link
              to="/customer"
              className="inline-block bg-primary hover:bg-primary-light text-white text-xs font-semibold px-5 py-2.5 rounded-xl transition-colors shadow-xs mt-2"
            >
              Cari Toko Laundry Terdekat
            </Link>
          </div>
        ) : (
          <div className="space-y-4">
            {filteredOrders.map((order) => {
              const cfg = STATUS_CONFIG[order.status] || {
                label: order.status,
                color: 'bg-gray-100 text-gray-700 border-gray-200',
                step: 1,
              };
              const isCancelled = order.status === 'dibatalkan';
              const isCompleted = order.status === 'selesai';
              const canCancel = ['menunggu_konfirmasi', 'menunggu_kurir'].includes(order.status);
              const hasReviews = order.reviews && order.reviews.length > 0;
              const activeDispute = order.disputes?.find((d) => ['open', 'in_review', 'under_review'].includes(d.status));
              const resolvedDispute = order.disputes?.find((d) => ['resolved', 'rejected'].includes(d.status));

              return (
                <div
                  key={order.id}
                  className="bg-white rounded-2xl border border-gray-200/90 shadow-xs hover:shadow-md transition-all overflow-hidden p-5 space-y-4"
                >
                  {/* Card Header */}
                  <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 pb-3">
                    <div className="space-y-0.5">
                      <div className="flex items-center gap-2">
                        <span className="font-bold text-sm text-gray-900">
                          {order.toko?.nama || 'Toko Laundry'}
                        </span>
                        <span className="text-[11px] font-mono text-gray-400">
                          #ORD-{order.id}
                        </span>
                      </div>
                      <p className="text-xs text-gray-500">
                        Layanan: <strong>{order.tarif?.jenis_layanan || 'Reguler'}</strong> • {new Date(order.created_at).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })}
                      </p>
                    </div>

                    <span
                      className={`text-xs font-bold px-3 py-1 rounded-full border self-start sm:self-auto ${cfg.color}`}
                    >
                      {cfg.label}
                    </span>
                  </div>

                  {/* Visual Stepper Tracker (Hanya tampil jika tidak dibatalkan) */}
                  {!isCancelled && (
                    <div className="py-2">
                      <div className="grid grid-cols-5 sm:grid-cols-9 gap-1 text-center">
                        {STEP_LABELS.map((label, idx) => {
                          const stepNumber = idx + 1;
                          const isDone = cfg.step >= stepNumber;
                          const isCurrent = cfg.step === stepNumber;

                          return (
                            <div key={label} className="flex flex-col items-center space-y-1">
                              <div
                                className={`w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold transition-all ${
                                  isDone
                                    ? 'bg-emerald-500 text-white shadow-xs'
                                    : 'bg-gray-100 text-gray-400'
                                } ${isCurrent ? 'ring-2 ring-emerald-300 scale-110' : ''}`}
                              >
                                {isDone ? '✓' : stepNumber}
                              </div>
                              <span
                                className={`text-[9px] leading-tight line-clamp-1 ${
                                  isCurrent
                                    ? 'font-bold text-gray-900'
                                    : isDone
                                    ? 'text-gray-600 font-medium'
                                    : 'text-gray-400'
                                }`}
                              >
                                {label}
                              </span>
                            </div>
                          );
                        })}
                      </div>
                    </div>
                  )}

                  {/* Highlight Biaya & Timbangan (Jika sudah ditimbang) */}
                  {order.total_biaya ? (
                    <div className="bg-emerald-50/70 border border-emerald-200 p-3.5 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                      <div>
                        <p className="font-bold text-emerald-900">
                          ⚖️ Berat Cucian: {order.berat} kg • Tagihan COD:
                        </p>
                        <p className="text-[11px] text-emerald-700">
                          Siapkan uang tunai saat kurir mengantar kembali pakaian bersih Anda.
                        </p>
                      </div>
                      <span className="font-mono text-base font-extrabold text-emerald-800">
                        Rp {Number(order.total_biaya).toLocaleString('id-ID')}
                      </span>
                    </div>
                  ) : (
                    <div className="bg-neutral p-3 rounded-xl border border-gray-200/60 text-xs text-gray-500 flex items-center gap-2">
                      <span>ℹ️</span>
                      <span>
                        Pakaian belum ditimbang di toko. Total biaya akan dihitung otomatis setelah tiba di toko laundry.
                      </span>
                    </div>
                  )}

                  {/* Kurir & Alamat Info */}
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1 text-xs text-gray-600">
                    <div>
                      <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">
                        Alamat Penjemputan:
                      </p>
                      <p className="text-xs text-gray-700 line-clamp-2 mt-0.5">
                        📍 {order.alamat_jemput}
                      </p>
                    </div>

                    <div>
                      <p className="text-[10px] uppercase font-bold text-gray-400 tracking-wider">
                        Mitra Kurir:
                      </p>
                      {order.kurir ? (
                        <p className="text-xs font-semibold text-gray-800 flex items-center gap-1.5 mt-0.5">
                          <span>🛵</span> {order.kurir.name}
                        </p>
                      ) : (
                        <p className="text-xs text-gray-400 italic mt-0.5">
                          Menunggu kurir menerima tugas...
                        </p>
                      )}
                    </div>
                  </div>

                  {/* Active Dispute Banner */}
                  {activeDispute && (
                    <div className="bg-amber-50 border border-amber-200 p-3 rounded-xl flex items-center justify-between text-xs text-amber-900">
                      <div className="flex items-center gap-2">
                        <span className="text-base">⚠️</span>
                        <div>
                          <p className="font-bold">
                            Komplain Aktif: {activeDispute.alasan ? activeDispute.alasan.replace(/_/g, ' ').toUpperCase() : 'DISPUTE'}
                          </p>
                          <p className="text-[11px] text-amber-700 mt-0.5 line-clamp-1">{activeDispute.deskripsi}</p>
                        </div>
                      </div>
                      <span className="shrink-0 bg-amber-200 text-amber-900 font-bold px-2 py-0.5 rounded-full text-[10px]">
                        {activeDispute.status === 'open' ? 'Menunggu Review Admin' : 'Sedang Ditinjau Admin'}
                      </span>
                    </div>
                  )}

                  {/* Resolved Dispute Banner */}
                  {resolvedDispute && !activeDispute && (
                    <div className={`p-3 rounded-xl border text-xs flex items-center justify-between ${
                      resolvedDispute.status === 'resolved'
                        ? 'bg-emerald-50 border-emerald-200 text-emerald-900'
                        : 'bg-rose-50 border-rose-200 text-rose-900'
                    }`}>
                      <div className="flex items-center gap-2">
                        <span className="text-base">{resolvedDispute.status === 'resolved' ? '✅' : '❌'}</span>
                        <div>
                          <p className="font-bold">
                            Dispute {resolvedDispute.status === 'resolved' ? 'Diselesaikan (Resolved)' : 'Ditolak (Rejected)'}
                          </p>
                          <p className="text-[11px] mt-0.5">
                            <strong>Resolusi Admin:</strong> {resolvedDispute.resolusi || '(Selesai tanpa catatan tambahan)'}
                          </p>
                        </div>
                      </div>
                    </div>
                  )}

                  {/* Tracking Kurir Real-time */}
                  {(order.status === 'dijemput' || order.status === 'diantar') && (
                    <div className="mt-1">
                      <KurirTrackingMap orderId={order.id} isVisible={true} />
                    </div>
                  )}

                  {/* Card Action Buttons */}
                  <div className="border-t border-gray-100 pt-3 flex flex-wrap items-center justify-between gap-2">
                    <button
                      onClick={() => handleOpenTimeline(order.id)}
                      className="text-xs font-semibold text-secondary hover:text-secondary-dark flex items-center gap-1 cursor-pointer"
                    >
                      <span>📜</span> Lihat Timeline Riwayat
                    </button>

                    <div className="flex items-center gap-2 ml-auto">
                      {/* Tombol Cancel (Hanya aktif di Cancel Window) */}
                      {canCancel && (
                        <button
                          onClick={() => {
                            setSelectedOrder(order);
                            setCancelReason('');
                            setShowCancelModal(true);
                          }}
                          className="text-xs bg-rose-50 hover:bg-rose-100 text-rose-700 font-semibold px-3 py-1.5 rounded-lg border border-rose-200 transition-colors cursor-pointer"
                        >
                          Batalkan Pesanan
                        </button>
                      )}

                      {/* Tombol Ajukan Dispute (Jika belum ada dispute aktif dan order tidak dibatalkan) */}
                      {!activeDispute && !isCancelled && (
                        <button
                          onClick={() => handleOpenDispute(order)}
                          className="text-xs bg-amber-50 hover:bg-amber-100 text-amber-800 font-semibold px-3 py-1.5 rounded-lg border border-amber-200 transition-colors cursor-pointer"
                        >
                          ⚠️ Ajukan Dispute
                        </button>
                      )}

                      {/* Tombol Beri Ulasan (Hanya saat status 'selesai') */}
                      {isCompleted && !hasReviews && (
                        <button
                          onClick={() => handleOpenReview(order)}
                          className="text-xs bg-amber-500 hover:bg-amber-600 text-white font-bold px-3.5 py-1.5 rounded-lg transition-colors shadow-xs cursor-pointer"
                        >
                          ⭐ Beri Ulasan Toko & Kurir
                        </button>
                      )}

                      {isCompleted && hasReviews && (
                        <span className="text-xs text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-md font-semibold">
                          ✓ Sudah Diulas
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {/* Timeline Modal */}
      {showTimelineModal && selectedOrder && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4 max-h-[85vh] overflow-y-auto">
            <div className="flex items-start justify-between border-b border-gray-100 pb-3">
              <div>
                <h3 className="font-bold text-gray-900 text-base">
                  Riwayat Status #ORD-{selectedOrder.id}
                </h3>
                <p className="text-xs text-gray-500">{selectedOrder.toko?.nama}</p>
              </div>
              <button
                onClick={() => setShowTimelineModal(false)}
                className="text-gray-400 hover:text-gray-600 font-bold"
              >
                ✕
              </button>
            </div>

            <div className="relative border-l-2 border-gray-200 ml-3 space-y-5 my-2">
              {selectedOrder.status_histories?.map((h, i) => (
                <div key={h.id || i} className="ml-5 relative">
                  <span className="absolute -left-[27px] top-1 w-3 h-3 rounded-full bg-secondary ring-4 ring-white" />
                  <p className="text-xs font-bold text-gray-800">
                    {STATUS_CONFIG[h.status]?.label || h.status}
                  </p>
                  <p className="text-[11px] text-gray-600 mt-0.5 leading-relaxed">
                    {h.keterangan || 'Status diperbarui.'}
                  </p>
                  <span className="text-[10px] text-gray-400">
                    {new Date(h.created_at).toLocaleString('id-ID')}
                  </span>
                </div>
              ))}
            </div>

            <div className="pt-2 border-t border-gray-100 flex justify-end">
              <button
                onClick={() => setShowTimelineModal(false)}
                className="bg-gray-100 text-gray-700 px-4 py-2 rounded-xl text-xs font-semibold hover:bg-gray-200"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Review Modal (Toko & Kurir Terpisah) */}
      {showReviewModal && selectedOrder && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-5">
            <div className="flex items-start justify-between border-b border-gray-100 pb-3">
              <div>
                <h3 className="font-bold text-gray-900 text-base">Beri Ulasan Pesanan</h3>
                <p className="text-xs text-gray-500">
                  Ulasan terpisah untuk mitra toko dan kurir pengantar
                </p>
              </div>
              <button
                onClick={() => setShowReviewModal(false)}
                className="text-gray-400 hover:text-gray-600 font-bold"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleSubmitReview} className="space-y-4">
              {/* Review Toko */}
              <div className="bg-neutral p-4 rounded-xl border border-gray-200/80 space-y-2">
                <p className="text-xs font-bold text-gray-800 flex items-center gap-1.5">
                  <span>🏪</span> Ulasan Toko: {selectedOrder.toko?.nama}
                </p>
                <div className="flex items-center gap-1">
                  {[1, 2, 3, 4, 5].map((star) => (
                    <button
                      type="button"
                      key={star}
                      onClick={() => setTokoRating(star)}
                      className="text-xl text-amber-400 hover:scale-125 transition-transform cursor-pointer"
                    >
                      {tokoRating >= star ? '★' : '☆'}
                    </button>
                  ))}
                  <span className="text-xs font-bold text-gray-600 ml-2">
                    {tokoRating} / 5
                  </span>
                </div>
                <input
                  type="text"
                  placeholder="Komentar hasil cucian, kerapian, wangi... (opsional)"
                  value={tokoKomentar}
                  onChange={(e) => setTokoKomentar(e.target.value)}
                  className="w-full text-xs bg-white border border-gray-300 rounded-lg p-2 focus:ring-1 focus:ring-secondary focus:outline-none"
                />
              </div>

              {/* Review Kurir */}
              {selectedOrder.kurir && (
                <div className="bg-neutral p-4 rounded-xl border border-gray-200/80 space-y-2">
                  <p className="text-xs font-bold text-gray-800 flex items-center gap-1.5">
                    <span>🛵</span> Ulasan Kurir: {selectedOrder.kurir.name}
                  </p>
                  <div className="flex items-center gap-1">
                    {[1, 2, 3, 4, 5].map((star) => (
                      <button
                        type="button"
                        key={star}
                        onClick={() => setKurirRating(star)}
                        className="text-xl text-amber-400 hover:scale-125 transition-transform cursor-pointer"
                      >
                        {kurirRating >= star ? '★' : '☆'}
                      </button>
                    ))}
                    <span className="text-xs font-bold text-gray-600 ml-2">
                      {kurirRating} / 5
                    </span>
                  </div>
                  <input
                    type="text"
                    placeholder="Komentar kecepatan, keramahan kurir... (opsional)"
                    value={kurirKomentar}
                    onChange={(e) => setKurirKomentar(e.target.value)}
                    className="w-full text-xs bg-white border border-gray-300 rounded-lg p-2 focus:ring-1 focus:ring-secondary focus:outline-none"
                  />
                </div>
              )}

              <div className="flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button
                  type="button"
                  onClick={() => setShowReviewModal(false)}
                  className="px-4 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-100 rounded-xl"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={submittingReview}
                  className="px-5 py-2 text-xs font-bold bg-secondary hover:bg-secondary-dark text-white rounded-xl shadow-xs"
                >
                  {submittingReview ? 'Mengirim...' : 'Kirim Ulasan ⭐'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Cancel Order Modal */}
      {showCancelModal && selectedOrder && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
            <div className="text-center space-y-2">
              <div className="text-4xl">⚠️</div>
              <h3 className="text-base font-bold text-gray-900">
                Batalkan Pesanan #ORD-{selectedOrder.id}?
              </h3>
              <p className="text-xs text-gray-500">
                Pembatalan hanya berlaku karena pakaian belum dijemput oleh kurir.
              </p>
            </div>

            <textarea
              rows={2}
              placeholder="Alasan pembatalan (opsional)..."
              value={cancelReason}
              onChange={(e) => setCancelReason(e.target.value)}
              className="w-full text-xs border border-gray-300 rounded-xl p-2.5 focus:ring-1 focus:ring-rose-500 focus:outline-none"
            />

            <div className="flex gap-2 pt-2">
              <button
                type="button"
                onClick={() => setShowCancelModal(false)}
                className="flex-1 py-2 text-xs font-semibold text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50"
              >
                Kembali
              </button>
              <button
                type="button"
                disabled={cancelling}
                onClick={handleCancelOrder}
                className="flex-1 py-2 text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white rounded-xl shadow-xs"
              >
                {cancelling ? 'Membatalkan...' : 'Ya, Batalkan'}
              </button>
            </div>
          </div>
        </div>
      )}
      {/* Dispute Modal */}
      {showDisputeModal && disputeOrder && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b pb-3">
              <div>
                <h3 className="font-bold text-sm text-gray-900">
                  Buka Dispute / Komplain
                </h3>
                <p className="text-xs text-gray-500">
                  Pesanan #{disputeOrder.nomor_order || `ORD-${disputeOrder.id}`} • {disputeOrder.toko?.nama}
                </p>
              </div>
              <button
                onClick={() => setShowDisputeModal(false)}
                className="text-gray-400 hover:text-gray-600 font-bold"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleSubmitDispute} className="space-y-4 text-xs">
              <div>
                <label className="block font-bold text-gray-700 mb-1">
                  Alasan Kendala:
                </label>
                <select
                  value={disputeReason}
                  onChange={(e) => setDisputeReason(e.target.value)}
                  className="w-full bg-white border border-gray-300 rounded-xl p-2.5 text-xs outline-none focus:ring-1 focus:ring-primary"
                >
                  <option value="berat_tidak_sesuai">⚖️ Berat Cucian Tidak Sesuai</option>
                  <option value="barang_rusak">👕 Pakaian Rusak / Robek / Luntur</option>
                  <option value="barang_hilang">❓ Pakaian Tertinggal / Hilang</option>
                  <option value="keterlambatan">⏳ Keterlambatan Ekstrem</option>
                  <option value="layanan_kurang_memuaskan">👎 Kualitas Cucian Kurang Bersih / Bau</option>
                  <option value="lainnya">📝 Lainnya / Masalah Lain</option>
                </select>
              </div>

              <div>
                <label className="block font-bold text-gray-700 mb-1">
                  Deskripsi Lengkap <span className="text-rose-500">*</span>:
                </label>
                <textarea
                  rows={4}
                  required
                  placeholder="Ceritakan detail kendala yang Anda alami secara transparan..."
                  value={disputeDescription}
                  onChange={(e) => setDisputeDescription(e.target.value)}
                  className="w-full bg-white border border-gray-300 rounded-xl p-2.5 text-xs outline-none focus:ring-1 focus:ring-primary"
                />
                <p className="text-[10px] text-gray-400 mt-1">Minimal 10 karakter.</p>
              </div>

              <div className="bg-blue-50 border border-blue-200 rounded-xl p-3 text-[11px] text-blue-800 leading-relaxed">
                ℹ️ Laporan dispute akan segera ditinjau oleh Admin Platform & Toko terkait untuk penyelesaian ganti rugi atau klarifikasi.
              </div>

              <div className="flex gap-2 justify-end pt-2 border-t">
                <button
                  type="button"
                  onClick={() => setShowDisputeModal(false)}
                  className="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-4 py-2 rounded-xl"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={submittingDispute || disputeDescription.trim().length < 10}
                  className="bg-amber-600 hover:bg-amber-700 text-white font-bold px-4 py-2 rounded-xl disabled:opacity-50 cursor-pointer shadow-xs"
                >
                  {submittingDispute ? 'Mengirim...' : 'Kirim Komplain Dispute'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
