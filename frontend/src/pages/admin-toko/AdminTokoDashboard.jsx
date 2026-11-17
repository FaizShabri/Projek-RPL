import { useEffect, useState } from 'react';
import api from '../../api/client';
import DashboardLayout from '../../components/dashboard/DashboardLayout';
import StatusBadge from '../../components/dashboard/StatusBadge';

export default function AdminTokoDashboard() {
  const [tokos, setTokos] = useState([]);
  const [selectedToko, setSelectedToko] = useState(null);
  const [loading, setLoading] = useState(true);
  const [orders, setOrders] = useState([]);
  const [ordersLoading, setOrdersLoading] = useState(false);
  const [orderFilter, setOrderFilter] = useState('all');
  const [actionLoadingId, setActionLoadingId] = useState(null);

  // Weighing modal
  const [weighModalOpen, setWeighModalOpen] = useState(false);
  const [orderToWeigh, setOrderToWeigh] = useState(null);
  const [inputBerat, setInputBerat] = useState('');
  const [submittingWeigh, setSubmittingWeigh] = useState(false);

  // Dispute modal state
  const [showDisputeModal, setShowDisputeModal] = useState(false);
  const [disputeOrder, setDisputeOrder] = useState(null);
  const [disputeReason, setDisputeReason] = useState('barang_rusak');
  const [disputeDescription, setDisputeDescription] = useState('');
  const [submittingDispute, setSubmittingDispute] = useState(false);

  const fetchAssignedTokos = async () => {
    try {
      setLoading(true);
      const res = await api.get('/owner/tokos');
      const list = res.tokos || [];
      setTokos(list);
      if (list.length > 0) {
        setSelectedToko(list[0]);
      }
    } catch (err) {
      console.error('Error fetching assigned tokos:', err);
    } finally {
      setLoading(false);
    }
  };

  const fetchOrders = async (tokoId) => {
    if (!tokoId) return;
    try {
      setOrdersLoading(true);
      const res = await api.get(`/owner/tokos/${tokoId}/orders`);
      setOrders(res.data || []);
    } catch (err) {
      console.error('Error fetching orders:', err);
    } finally {
      setOrdersLoading(false);
    }
  };

  useEffect(() => {
    fetchAssignedTokos();
  }, []);

  useEffect(() => {
    if (selectedToko?.id) {
      fetchOrders(selectedToko.id);
    }
  }, [selectedToko?.id]);

  const handleConfirmOrder = async (orderId) => {
    setActionLoadingId(orderId);
    try {
      await api.post(`/owner/orders/${orderId}/confirm`);
      await fetchOrders(selectedToko.id);
    } catch (err) {
      alert('Gagal konfirmasi order: ' + err.message);
    } finally {
      setActionLoadingId(null);
    }
  };

  const handleWeighSubmit = async (e) => {
    e.preventDefault();
    if (!orderToWeigh || !inputBerat) return;
    setSubmittingWeigh(true);
    try {
      const res = await api.post(`/owner/orders/${orderToWeigh.id}/weigh`, {
        berat: parseFloat(inputBerat),
      });
      alert(`Sukses! Berat: ${inputBerat} kg. Total tagihan COD: Rp ${Number(res.total_biaya).toLocaleString('id-ID')}`);
      setWeighModalOpen(false);
      setOrderToWeigh(null);
      setInputBerat('');
      await fetchOrders(selectedToko.id);
    } catch (err) {
      alert('Gagal simpan berat: ' + err.message);
    } finally {
      setSubmittingWeigh(false);
    }
  };

  const handleStatusChange = async (orderId, newStatus) => {
    setActionLoadingId(orderId);
    try {
      await api.post(`/owner/orders/${orderId}/status`, { status: newStatus });
      await fetchOrders(selectedToko.id);
    } catch (err) {
      alert('Gagal perbarui status: ' + err.message);
    } finally {
      setActionLoadingId(null);
    }
  };

  const handleOpenDispute = (order) => {
    setDisputeOrder(order);
    setDisputeReason('barang_rusak');
    setDisputeDescription('');
    setShowDisputeModal(true);
  };

  const handleSubmitDispute = async (e) => {
    e.preventDefault();
    if (!disputeOrder || !selectedToko) return;
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
      alert('Dispute / kendala pesanan berhasil dilaporkan ke Admin Platform.');
      setShowDisputeModal(false);
      setDisputeOrder(null);
      setDisputeDescription('');
      fetchOrders(selectedToko.id);
    } catch (err) {
      alert('Gagal mengajukan dispute: ' + err.message);
    } finally {
      setSubmittingDispute(false);
    }
  };

  const filteredOrders = orders.filter((o) => {
    if (orderFilter === 'needs_action') {
      return ['menunggu_konfirmasi', 'di_toko', 'menunggu_pembayaran_info', 'diproses'].includes(o.status);
    }
    if (orderFilter === 'delivery') {
      return ['menunggu_kurir', 'dijemput', 'siap_diantar', 'diantar'].includes(o.status);
    }
    if (orderFilter === 'completed') {
      return ['cod_selesai', 'selesai', 'dibatalkan'].includes(o.status);
    }
    return true;
  });

  return (
    <DashboardLayout
      requiredRole="admin_toko"
      title="Admin Toko Dashboard"
      subtitle="Kelola operasional cucian & update status pesanan toko yang ditugaskan kepada Anda."
      badgeIcon="🧺"
      navItems={[
        { label: 'Pesanan Masuk', icon: '📦', path: '#' },
      ]}
    >
      {/* Toko Scoped Info Card */}
      {selectedToko && (
        <div className="bg-white rounded-2xl border border-gray-200 p-5 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="flex items-center gap-3.5">
            <div className="w-12 h-12 rounded-xl bg-primary text-white flex items-center justify-center text-xl font-bold">
              🏪
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h2 className="text-base font-bold text-gray-900">{selectedToko.nama}</h2>
                <StatusBadge status={selectedToko.status} />
              </div>
              <p className="text-xs text-gray-500 mt-0.5">📍 {selectedToko.alamat}</p>
              <span className="inline-block mt-1 text-[10px] bg-secondary/10 text-secondary font-bold px-2 py-0.5 rounded">
                Role: Staff Operasional Toko
              </span>
            </div>
          </div>

          {tokos.length > 1 && (
            <div className="flex items-center gap-2">
              <label className="text-xs font-semibold text-gray-500">Pilih Toko:</label>
              <select
                value={selectedToko.id}
                onChange={(e) => {
                  const t = tokos.find((x) => x.id === Number(e.target.value));
                  if (t) setSelectedToko(t);
                }}
                className="bg-neutral border border-gray-300 rounded-lg p-1.5 text-xs font-medium"
              >
                {tokos.map((t) => (
                  <option key={t.id} value={t.id}>{t.nama}</option>
                ))}
              </select>
            </div>
          )}
        </div>
      )}

      {loading ? (
        <div className="p-12 text-center bg-white rounded-2xl border border-gray-200 shadow-xs">
          <div className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin mx-auto mb-2" />
          <p className="text-xs text-gray-500">Memuat data toko tempat bertugas...</p>
        </div>
      ) : !selectedToko ? (
        <div className="bg-white p-12 text-center rounded-2xl border border-gray-200">
          <p className="text-3xl mb-2">🏪</p>
          <h3 className="font-bold text-sm text-gray-800">Belum Ada Toko yang Ditugaskan</h3>
          <p className="text-xs text-gray-500 mt-1">
            Anda belum ditugaskan sebagai Admin Toko oleh pemilik laundry mana pun.
          </p>
        </div>
      ) : (
        <div className="bg-white rounded-2xl border border-gray-200 shadow-xs p-5 space-y-5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b pb-4">
            <div>
              <h3 className="font-bold text-sm text-gray-900">Antrean Pesanan Masuk ({orders.length})</h3>
              <p className="text-xs text-gray-500">
                Konfirmasi pesanan baru, timbang pakaian saat tiba, dan update status pencucian.
              </p>
            </div>
            <button
              onClick={() => fetchOrders(selectedToko.id)}
              className="text-xs text-secondary hover:text-secondary-dark font-semibold flex items-center gap-1 self-start sm:self-auto"
            >
              🔄 Refresh Pesanan
            </button>
          </div>

          {/* Filter Chips */}
          <div className="flex flex-wrap gap-2 text-xs">
            <button
              onClick={() => setOrderFilter('all')}
              className={`px-3 py-1.5 rounded-lg font-bold transition-colors cursor-pointer ${
                orderFilter === 'all' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
              }`}
            >
              Semua ({orders.length})
            </button>
            <button
              onClick={() => setOrderFilter('needs_action')}
              className={`px-3 py-1.5 rounded-lg font-bold transition-colors cursor-pointer ${
                orderFilter === 'needs_action'
                  ? 'bg-amber-600 text-white'
                  : 'bg-amber-50 text-amber-800 border border-amber-200'
              }`}
            >
              ⚡ Perlu Tindakan ({orders.filter((o) => ['menunggu_konfirmasi', 'di_toko', 'menunggu_pembayaran_info', 'diproses'].includes(o.status)).length})
            </button>
            <button
              onClick={() => setOrderFilter('delivery')}
              className={`px-3 py-1.5 rounded-lg font-bold transition-colors cursor-pointer ${
                orderFilter === 'delivery'
                  ? 'bg-blue-600 text-white'
                  : 'bg-blue-50 text-blue-800 border border-blue-200'
              }`}
            >
              🛵 Pengantaran ({orders.filter((o) => ['menunggu_kurir', 'dijemput', 'siap_diantar', 'diantar'].includes(o.status)).length})
            </button>
            <button
              onClick={() => setOrderFilter('completed')}
              className={`px-3 py-1.5 rounded-lg font-bold transition-colors cursor-pointer ${
                orderFilter === 'completed'
                  ? 'bg-emerald-600 text-white'
                  : 'bg-emerald-50 text-emerald-800 border border-emerald-200'
              }`}
            >
              ✅ Selesai ({orders.filter((o) => ['cod_selesai', 'selesai', 'dibatalkan'].includes(o.status)).length})
            </button>
          </div>

          {/* Orders Cards List */}
          {ordersLoading ? (
            <div className="py-12 text-center text-xs text-gray-400">Memuat pesanan toko...</div>
          ) : filteredOrders.length === 0 ? (
            <div className="py-12 text-center text-xs text-gray-400 border border-dashed rounded-xl bg-neutral">
              Tidak ada pesanan pada filter ini.
            </div>
          ) : (
            <div className="space-y-4">
              {filteredOrders.map((order) => {
                const isActionLoading = actionLoadingId === order.id;
                const activeDispute = order.disputes?.find((d) => ['open', 'in_review', 'under_review'].includes(d.status));
                const resolvedDispute = order.disputes?.find((d) => ['resolved', 'rejected'].includes(d.status));

                return (
                  <div key={order.id} className="border border-gray-200 rounded-xl p-4 bg-white shadow-xs space-y-3">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b pb-2.5">
                      <div className="flex items-center gap-2">
                        <span className="font-mono font-bold text-sm text-primary">{order.nomor_order}</span>
                        <StatusBadge status={order.status} />
                      </div>
                      <p className="text-xs text-gray-500">
                        Customer: <strong className="text-gray-800">{order.customer?.name}</strong>
                      </p>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-neutral p-3 rounded-lg">
                      <div>
                        <p className="text-gray-500">Layanan: <strong className="text-gray-800">{order.tarif?.jenis_layanan}</strong></p>
                        <p className="text-gray-500">Tarif: Rp {Number(order.tarif?.harga || 0).toLocaleString('id-ID')} / kg</p>
                      </div>
                      <div>
                        <p className="text-gray-500">Berat: <strong className="text-gray-800">{order.berat ? `${order.berat} kg` : 'Belum ditimbang'}</strong></p>
                        <p className="text-gray-500">
                          Total COD: <strong className="text-emerald-700 font-mono text-sm">{order.total_biaya ? `Rp ${Number(order.total_biaya).toLocaleString('id-ID')}` : '-'}</strong>
                        </p>
                      </div>
                    </div>

                    {/* Active & Resolved Dispute Banners */}
                    {activeDispute && (
                      <div className="bg-amber-50 border border-amber-200 p-2.5 rounded-lg flex items-center justify-between text-xs text-amber-900">
                        <div>
                          <p className="font-bold">⚠️ Komplain Dispute: {activeDispute.alasan ? activeDispute.alasan.replace(/_/g, ' ').toUpperCase() : 'DISPUTE'}</p>
                          <p className="text-[11px] text-amber-700">{activeDispute.deskripsi}</p>
                        </div>
                        <span className="bg-amber-200 text-amber-900 font-bold px-2 py-0.5 rounded text-[10px]">
                          {activeDispute.status}
                        </span>
                      </div>
                    )}

                    {resolvedDispute && !activeDispute && (
                      <div className="bg-emerald-50 border border-emerald-200 p-2.5 rounded-lg text-xs text-emerald-900">
                        <p className="font-bold">✓ Dispute {resolvedDispute.status === 'resolved' ? 'Diselesaikan' : 'Ditolak'}: {resolvedDispute.resolusi || '-'}</p>
                      </div>
                    )}

                    {/* Operational Actions */}
                    <div className="pt-1 flex flex-wrap items-center justify-end gap-2">
                      {!activeDispute && order.status !== 'dibatalkan' && (
                        <button
                          type="button"
                          onClick={() => handleOpenDispute(order)}
                          className="text-[11px] text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 font-semibold px-2.5 py-1.5 rounded-lg transition-colors mr-auto"
                        >
                          ⚠️ Laporkan Dispute
                        </button>
                      )}
                      {order.status === 'menunggu_konfirmasi' && (
                        <button
                          onClick={() => handleConfirmOrder(order.id)}
                          disabled={isActionLoading}
                          className="bg-primary hover:bg-primary-dark text-white font-bold text-xs px-3.5 py-1.5 rounded-lg shadow-xs cursor-pointer"
                        >
                          {isActionLoading ? 'Memproses...' : '✓ Konfirmasi Order Masuk'}
                        </button>
                      )}

                      {order.status === 'di_toko' && (
                        <button
                          onClick={() => {
                            setOrderToWeigh(order);
                            setInputBerat(order.berat ? String(order.berat) : '');
                            setWeighModalOpen(true);
                          }}
                          className="bg-tertiary hover:bg-tertiary-dark text-white font-bold text-xs px-3.5 py-1.5 rounded-lg shadow-xs cursor-pointer"
                        >
                          ⚖️ Input Timbangan & Hitung COD
                        </button>
                      )}

                      {order.status === 'menunggu_pembayaran_info' && (
                        <button
                          onClick={() => handleStatusChange(order.id, 'diproses')}
                          disabled={isActionLoading}
                          className="bg-primary hover:bg-primary-dark text-white font-bold text-xs px-3.5 py-1.5 rounded-lg shadow-xs cursor-pointer"
                        >
                          🧺 Mulai Cuci Pakaian
                        </button>
                      )}

                      {order.status === 'diproses' && (
                        <button
                          onClick={() => handleStatusChange(order.id, 'siap_diantar')}
                          disabled={isActionLoading}
                          className="bg-secondary hover:bg-secondary-dark text-white font-bold text-xs px-3.5 py-1.5 rounded-lg shadow-xs cursor-pointer"
                        >
                          ✨ Selesai Cuci (Siap Diantar Kurir)
                        </button>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* Modal Timbang Berat */}
      {weighModalOpen && orderToWeigh && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-2xl max-w-md w-full p-5 shadow-2xl space-y-4">
            <h4 className="font-bold text-sm text-gray-900">
              ⚖️ Timbang Cucian Order #{orderToWeigh.nomor_order}
            </h4>

            <form onSubmit={handleWeighSubmit} className="space-y-4 text-xs">
              <div>
                <label className="block font-bold text-gray-700 mb-1">Berat Pakaian (kg):</label>
                <div className="relative">
                  <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    required
                    placeholder="Misal: 3.5"
                    value={inputBerat}
                    onChange={(e) => setInputBerat(e.target.value)}
                    className="w-full bg-white border border-gray-300 rounded-lg p-2.5 text-sm font-bold font-mono outline-none"
                    autoFocus
                  />
                  <span className="absolute right-3 top-2.5 font-bold text-gray-400">KG</span>
                </div>
              </div>

              <div className="bg-tertiary/10 border border-tertiary/30 rounded-xl p-3">
                <p className="text-xs text-tertiary-dark font-medium">Kalkulasi Otomatis Tagihan COD:</p>
                <p className="text-xl font-black font-mono text-tertiary-dark mt-0.5">
                  Rp {((parseFloat(inputBerat) || 0) * (orderToWeigh.tarif?.harga || 0)).toLocaleString('id-ID')}
                </p>
              </div>

              <div className="flex gap-2 justify-end pt-2 border-t">
                <button
                  type="button"
                  onClick={() => { setWeighModalOpen(false); setOrderToWeigh(null); }}
                  className="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold px-4 py-2 rounded-xl"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={submittingWeigh || !inputBerat || parseFloat(inputBerat) <= 0}
                  className="bg-tertiary hover:bg-tertiary-dark text-white font-bold px-4 py-2 rounded-xl disabled:opacity-50"
                >
                  {submittingWeigh ? 'Menyimpan...' : 'Simpan & Kirim Biaya'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Dispute Modal Admin Toko */}
      {showDisputeModal && disputeOrder && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b pb-3">
              <div>
                <h3 className="font-bold text-sm text-gray-900">
                  Ajukan Dispute / Kendala Pesanan
                </h3>
                <p className="text-xs text-gray-500">
                  Pesanan #{disputeOrder.nomor_order || disputeOrder.id} • Customer: {disputeOrder.customer?.name}
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
                  <option value="barang_rusak">👕 Pakaian Rusak / Luntur Sejak Diterima</option>
                  <option value="berat_tidak_sesuai">⚖️ Selisih Berat / Indikasi Kecurangan</option>
                  <option value="barang_berbahaya">⚠️ Pakaian Mengandung Benda Berbahaya</option>
                  <option value="customer_unreachable">📞 Customer Tidak Bisa Dihubungi</option>
                  <option value="lainnya">📝 Kendala Lainnya</option>
                </select>
              </div>

              <div>
                <label className="block font-bold text-gray-700 mb-1">
                  Deskripsi Kendala (Minimal 10 karakter):
                </label>
                <textarea
                  rows={4}
                  required
                  placeholder="Jelaskan kondisi cucian atau kendala yang dihadapi oleh pihak operasional toko..."
                  value={disputeDescription}
                  onChange={(e) => setDisputeDescription(e.target.value)}
                  className="w-full bg-white border border-gray-300 rounded-xl p-2.5 text-xs outline-none focus:ring-1 focus:ring-primary"
                />
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
                  {submittingDispute ? 'Mengirim...' : 'Kirim Laporan Dispute'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </DashboardLayout>
  );
}
