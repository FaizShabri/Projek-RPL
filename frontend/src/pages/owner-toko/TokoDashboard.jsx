import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';
import DashboardLayout from '../../components/dashboard/DashboardLayout';

export default function TokoDashboard() {
  const { activeRole, user } = useAuth();
  const [tokos, setTokos] = useState([]);
  const [selectedToko, setSelectedToko] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // State untuk Tab (orders / tarifs / staff)
  const [activeTab, setActiveTab] = useState('orders');

  // State Pesanan Toko
  const [orders, setOrders] = useState([]);
  const [ordersLoading, setOrdersLoading] = useState(false);
  const [orderFilter, setOrderFilter] = useState('all');
  const [actionLoadingId, setActionLoadingId] = useState(null);

  // State Modal Input Berat & Hitung Biaya
  const [weighModalOpen, setWeighModalOpen] = useState(false);
  const [orderToWeigh, setOrderToWeigh] = useState(null);
  const [inputBerat, setInputBerat] = useState('');
  const [submittingWeigh, setSubmittingWeigh] = useState(false);

  // State form tarif baru
  const [newTarif, setNewTarif] = useState({ jenis_layanan: '', harga: '' });
  const [submittingTarif, setSubmittingTarif] = useState(false);

  // State form staff baru
  const [newStaffEmail, setNewStaffEmail] = useState('');
  const [submittingStaff, setSubmittingStaff] = useState(false);

  // State Dispute Toko
  const [showDisputeModal, setShowDisputeModal] = useState(false);
  const [disputeOrder, setDisputeOrder] = useState(null);
  const [disputeReason, setDisputeReason] = useState('barang_rusak');
  const [disputeDescription, setDisputeDescription] = useState('');
  const [submittingDispute, setSubmittingDispute] = useState(false);

  // State Settlement Toko
  const [settlementData, setSettlementData] = useState(null);
  const [settlementLoading, setSettlementLoading] = useState(false);

  // State Edit Profil Toko
  const [profileForm, setProfileForm] = useState({ nama: '', alamat: '' });
  const [savingProfile, setSavingProfile] = useState(false);
  const [profileSaved, setProfileSaved] = useState(false);

  const fetchSettlement = async (tokoId) => {
    if (!tokoId) return;
    setSettlementLoading(true);
    try {
      const res = await api.get(`/owner/tokos/${tokoId}/settlement`);
      setSettlementData(res);
    } catch (err) {
      console.error('Error fetching settlement:', err);
    } finally {
      setSettlementLoading(false);
    }
  };

  const handleSaveProfile = async (e) => {
    e.preventDefault();
    if (!selectedToko) return;
    setSavingProfile(true);
    setProfileSaved(false);
    try {
      await api.post(`/owner/tokos/${selectedToko.id}`, profileForm);
      setProfileSaved(true);
      const updated = await api.get(`/owner/tokos/${selectedToko.id}`);
      setSelectedToko(updated.toko);
      fetchTokos();
    } catch (err) {
      alert('Gagal memperbarui profil: ' + err.message);
    } finally {
      setSavingProfile(false);
    }
  };

  const fetchOrders = async (tokoId) => {
    if (!tokoId) return;
    try {
      setOrdersLoading(true);
      const res = await api.get(`/owner/tokos/${tokoId}/orders`);
      setOrders(res.data || []);
    } catch (err) {
      console.error('Failed to fetch store orders:', err);
    } finally {
      setOrdersLoading(false);
    }
  };

  const fetchTokos = async () => {
    try {
      setLoading(true);
      const res = await api.get('/owner/tokos');
      setTokos(res.tokos || []);
      if (res.tokos && res.tokos.length > 0) {
        setSelectedToko(res.tokos[0]);
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTokos();
  }, [activeRole]);

  const handleAddTarif = async (e) => {
    e.preventDefault();
    if (!selectedToko) return;
    setSubmittingTarif(true);
    try {
      await api.post(`/owner/tokos/${selectedToko.id}/tarifs`, {
        jenis_layanan: newTarif.jenis_layanan,
        harga: parseFloat(newTarif.harga),
      });
      setNewTarif({ jenis_layanan: '', harga: '' });
      // Refresh toko terpilih
      const updated = await api.get(`/owner/tokos/${selectedToko.id}`);
      setSelectedToko(updated.toko);
      fetchTokos();
    } catch (err) {
      alert('Gagal menambah tarif: ' + err.message);
    } finally {
      setSubmittingTarif(false);
    }
  };

  const handleDeleteTarif = async (tarifId) => {
    if (!confirm('Yakin ingin menghapus tarif layanan ini?')) return;
    try {
      await api.delete(`/owner/tarifs/${tarifId}`);
      const updated = await api.get(`/owner/tokos/${selectedToko.id}`);
      setSelectedToko(updated.toko);
      fetchTokos();
    } catch (err) {
      alert('Gagal menghapus tarif: ' + err.message);
    }
  };

  const handleAddStaff = async (e) => {
    e.preventDefault();
    if (!selectedToko) return;
    setSubmittingStaff(true);
    try {
      const res = await api.post(`/owner/tokos/${selectedToko.id}/staff`, {
        email: newStaffEmail,
      });
      alert(res.message);
      setNewStaffEmail('');
      const updated = await api.get(`/owner/tokos/${selectedToko.id}`);
      setSelectedToko(updated.toko);
      fetchTokos();
    } catch (err) {
      alert('Gagal menambah staff: ' + err.message);
    } finally {
      setSubmittingStaff(false);
    }
  };

  const handleRemoveStaff = async (staffTokoId) => {
    if (!confirm('Cabut akses admin toko ini?')) return;
    try {
      await api.delete(`/owner/tokos/${selectedToko.id}/staff/${staffTokoId}`);
      const updated = await api.get(`/owner/tokos/${selectedToko.id}`);
      setSelectedToko(updated.toko);
      fetchTokos();
    } catch (err) {
      alert('Gagal mencabut staff: ' + err.message);
    }
  };

  useEffect(() => {
    if (selectedToko?.id) {
      fetchOrders(selectedToko.id);
      setProfileForm({
        nama: selectedToko.nama || '',
        alamat: selectedToko.alamat || '',
      });
      if (activeTab === 'settlement') {
        fetchSettlement(selectedToko.id);
      }
    }
  }, [selectedToko?.id, activeTab]);

  const handleConfirmOrder = async (orderId) => {
    setActionLoadingId(orderId);
    try {
      await api.post(`/owner/orders/${orderId}/confirm`);
      await fetchOrders(selectedToko.id);
    } catch (err) {
      alert('Gagal mengonfirmasi pesanan: ' + err.message);
    } finally {
      setActionLoadingId(null);
    }
  };

  const openWeighModal = (order) => {
    setOrderToWeigh(order);
    setInputBerat(order.berat ? String(order.berat) : '');
    setWeighModalOpen(true);
  };

  const handleWeighSubmit = async (e) => {
    e.preventDefault();
    if (!orderToWeigh || !inputBerat) return;
    setSubmittingWeigh(true);
    try {
      const res = await api.post(`/owner/orders/${orderToWeigh.id}/weigh`, {
        berat: parseFloat(inputBerat),
      });
      alert(`Sukses! Berat pakaian: ${inputBerat} kg. Total tagihan COD: Rp ${Number(res.total_biaya).toLocaleString('id-ID')}`);
      setWeighModalOpen(false);
      setOrderToWeigh(null);
      setInputBerat('');
      await fetchOrders(selectedToko.id);
    } catch (err) {
      alert('Gagal input berat pakaian: ' + err.message);
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
      alert('Gagal memperbarui status: ' + err.message);
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
      alert('Dispute / kendala pesanan berhasil diajukan ke Admin Platform.');
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

  const getOrderStatusBadge = (status) => {
    switch (status) {
      case 'menunggu_konfirmasi':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800 border border-amber-200">⏳ Menunggu Konfirmasi</span>;
      case 'menunggu_kurir':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 border border-blue-200">🛵 Menunggu Kurir</span>;
      case 'dijemput':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-indigo-100 text-indigo-800 border border-indigo-200">🚚 Dijemput Kurir</span>;
      case 'di_toko':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-purple-100 text-purple-800 border border-purple-200 animate-pulse">⚖️ Di Toko (Perlu Timbang)</span>;
      case 'menunggu_pembayaran_info':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-sky-100 text-sky-800 border border-sky-200">📋 Info Biaya Terkirim</span>;
      case 'diproses':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-teal-100 text-teal-800 border border-teal-200">🧺 Sedang Dicuci</span>;
      case 'siap_diantar':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-cyan-100 text-cyan-800 border border-cyan-200">✨ Siap Diantar</span>;
      case 'diantar':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800 border border-blue-200">🚀 Sedang Diantar</span>;
      case 'cod_selesai':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">💵 COD Selesai</span>;
      case 'selesai':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">✅ Selesai</span>;
      case 'dibatalkan':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-rose-100 text-rose-800 border border-rose-200">❌ Dibatalkan</span>;
      default:
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">{status}</span>;
    }
  };

  const getStatusBadge = (status) => {
    switch (status) {
      case 'approved':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-emerald-100 text-emerald-800">Approved</span>;
      case 'submitted':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Submitted</span>;
      case 'rejected':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-rose-100 text-rose-800">Rejected</span>;
      case 'suspended':
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-200 text-gray-800">Suspended</span>;
      default:
        return <span className="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-700">{status}</span>;
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center p-12">
        <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  const isStoreOwner = selectedToko && selectedToko.owner_id === user?.id;

  return (
    <DashboardLayout
      requiredRole="owner_toko"
      title="Dashboard Mitra Toko"
      subtitle="Kelola operasional laundry, pesanan masuk, tarif, staff, dan riwayat settlement."
      badgeIcon="🏪"
      navItems={[
        { label: 'Pesanan Masuk', icon: '📦', path: '#' },
        { label: 'Tarif Layanan', icon: '🏷️', path: '#' },
        { label: 'Staff Toko', icon: '👥', path: '#' },
        { label: 'Settlement & Payout', icon: '💰', path: '#' },
        { label: 'Profil Toko', icon: '⚙️', path: '#' },
      ]}
    >
      <div className="space-y-6">
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
        <div>
          <h1 className="text-2xl font-bold text-primary">Manajemen Toko Mitra</h1>
          <p className="text-sm text-gray-500">
            Kelola operasional toko, tarif layanan, dan admin staff toko.
          </p>
        </div>
        <Link
          to="/owner-toko/register"
          className="inline-flex items-center gap-2 bg-secondary hover:bg-secondary-dark text-white text-sm font-medium px-4 py-2.5 rounded-lg shadow-sm transition-colors self-start sm:self-auto"
        >
          ➕ Daftarkan Toko Baru
        </Link>
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-sm">
          {error}
        </div>
      )}

      {tokos.length === 0 ? (
        <div className="bg-white text-center py-16 px-6 rounded-xl border border-gray-200">
          <div className="text-4xl mb-3">🏪</div>
          <h2 className="text-lg font-bold text-gray-800">Belum Ada Toko Terdaftar</h2>
          <p className="text-sm text-gray-500 max-w-md mx-auto mt-1 mb-6">
            Anda belum mendaftarkan toko laundry. Daftarkan toko pertama Anda untuk mulai menerima order pelanggan.
          </p>
          <Link
            to="/owner-toko/register"
            className="bg-primary hover:bg-primary-dark text-white text-sm font-medium px-5 py-2.5 rounded-lg transition-colors"
          >
            Mulai Onboarding Toko
          </Link>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Toko Selector Sidebar */}
          <div className="bg-white p-4 rounded-xl border border-gray-200 shadow-sm space-y-3">
            <h2 className="text-sm font-semibold text-gray-600 uppercase tracking-wider mb-2">
              Daftar Toko Anda ({tokos.length})
            </h2>
            <div className="space-y-2">
              {tokos.map((toko) => {
                const isSelected = selectedToko?.id === toko.id;
                return (
                  <button
                    key={toko.id}
                    onClick={() => setSelectedToko(toko)}
                    className={`w-full text-left p-3.5 rounded-lg border transition-all ${
                      isSelected
                        ? 'border-secondary bg-secondary/5 ring-1 ring-secondary'
                        : 'border-gray-200 hover:bg-gray-50'
                    }`}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div className="font-semibold text-gray-900 text-sm">{toko.nama}</div>
                      {getStatusBadge(toko.status)}
                    </div>
                    <p className="text-xs text-gray-500 mt-1 line-clamp-1">{toko.alamat}</p>
                    <div className="text-xs text-gray-400 mt-2 flex gap-3">
                      <span>🏷️ {toko.tarifs?.length || 0} Tarif</span>
                      <span>👥 {toko.staff_users?.length || 0} Staff</span>
                    </div>
                  </button>
                );
              })}
            </div>
          </div>

          {/* Toko Detail & Management Panels */}
          {selectedToko && (
            <div className="lg:col-span-2 space-y-6">
              {/* Info Card */}
              <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm">
                <div className="flex items-start justify-between flex-wrap gap-3">
                  <div>
                    <h2 className="text-xl font-bold text-primary">{selectedToko.nama}</h2>
                    <p className="text-sm text-gray-600 mt-1">{selectedToko.alamat}</p>
                    {selectedToko.latitude && selectedToko.longitude && (
                      <p className="text-xs text-gray-400 mt-1">
                        📍 Koordinat: {selectedToko.latitude.toFixed(6)}, {selectedToko.longitude.toFixed(6)}
                      </p>
                    )}
                  </div>
                  <div className="text-right">
                    {getStatusBadge(selectedToko.status)}
                    {selectedToko.status === 'submitted' && (
                      <p className="text-xs text-amber-600 mt-1">Sedang ditinjau Admin</p>
                    )}
                  </div>
                </div>

                {selectedToko.dokumen_legalitas && (
                  <div className="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-600">
                    <span>📄 Dokumen Legalitas terunggah</span>
                    <span className="text-secondary font-medium">Terverifikasi</span>
                  </div>
                )}
              </div>

              {/* Tabs */}
              <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div className="flex border-b border-gray-200 overflow-x-auto">
                  <button
                    onClick={() => setActiveTab('orders')}
                    className={`flex-1 min-w-[130px] py-3 px-4 text-sm font-semibold border-b-2 text-center transition-colors ${
                      activeTab === 'orders'
                        ? 'border-secondary text-secondary bg-secondary/5'
                        : 'border-transparent text-gray-500 hover:text-gray-700'
                    }`}
                  >
                    📦 Pesanan ({orders.length})
                  </button>
                  <button
                    onClick={() => setActiveTab('tarifs')}
                    className={`flex-1 min-w-[130px] py-3 px-4 text-sm font-semibold border-b-2 text-center transition-colors ${
                      activeTab === 'tarifs'
                        ? 'border-secondary text-secondary bg-secondary/5'
                        : 'border-transparent text-gray-500 hover:text-gray-700'
                    }`}
                  >
                    🏷️ Tarif Layanan ({selectedToko.tarifs?.length || 0})
                  </button>
                  <button
                    onClick={() => setActiveTab('staff')}
                    className={`flex-1 min-w-[130px] py-3 px-4 text-sm font-semibold border-b-2 text-center transition-colors ${
                      activeTab === 'staff'
                        ? 'border-secondary text-secondary bg-secondary/5'
                        : 'border-transparent text-gray-500 hover:text-gray-700'
                    }`}
                  >
                    👥 Staff ({selectedToko.staff_users?.length || 0})
                  </button>
                  <button
                    onClick={() => setActiveTab('settlement')}
                    className={`flex-1 min-w-[130px] py-3 px-4 text-sm font-semibold border-b-2 text-center transition-colors cursor-pointer ${
                      activeTab === 'settlement'
                        ? 'border-secondary text-secondary bg-secondary/5'
                        : 'border-transparent text-gray-500 hover:text-gray-700'
                    }`}
                  >
                    💰 Settlement & Payout
                  </button>
                  <button
                    onClick={() => setActiveTab('profile')}
                    className={`flex-1 min-w-[130px] py-3 px-4 text-sm font-semibold border-b-2 text-center transition-colors cursor-pointer ${
                      activeTab === 'profile'
                        ? 'border-secondary text-secondary bg-secondary/5'
                        : 'border-transparent text-gray-500 hover:text-gray-700'
                    }`}
                  >
                    🏪 Profil Toko
                  </button>
                </div>

                <div className="p-6">
                  {/* TAB 0: PESANAN MASUK */}
                  {activeTab === 'orders' && (
                    <div className="space-y-6">
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                          <h3 className="font-bold text-gray-900 text-sm">Pesanan Laundry Masuk</h3>
                          <p className="text-xs text-gray-500">
                            Kelola alur pengerjaan cucian pelanggan, penimbangan berat, dan kalkulasi tagihan COD.
                          </p>
                        </div>
                        <button
                          onClick={() => fetchOrders(selectedToko.id)}
                          className="self-start sm:self-auto text-xs font-medium text-secondary hover:text-secondary-dark flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-secondary/20 hover:bg-secondary/5 transition-colors"
                        >
                          🔄 Refresh Data
                        </button>
                      </div>

                      {/* Filter Chips */}
                      <div className="flex flex-wrap gap-2 text-xs">
                        <button
                          onClick={() => setOrderFilter('all')}
                          className={`px-3 py-1.5 rounded-lg font-medium transition-colors ${
                            orderFilter === 'all'
                              ? 'bg-primary text-white'
                              : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                          }`}
                        >
                          Semua ({orders.length})
                        </button>
                        <button
                          onClick={() => setOrderFilter('needs_action')}
                          className={`px-3 py-1.5 rounded-lg font-medium transition-colors ${
                            orderFilter === 'needs_action'
                              ? 'bg-amber-600 text-white'
                              : 'bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200'
                          }`}
                        >
                          ⚡ Perlu Tindakan ({orders.filter((o) => ['menunggu_konfirmasi', 'di_toko', 'menunggu_pembayaran_info', 'diproses'].includes(o.status)).length})
                        </button>
                        <button
                          onClick={() => setOrderFilter('delivery')}
                          className={`px-3 py-1.5 rounded-lg font-medium transition-colors ${
                            orderFilter === 'delivery'
                              ? 'bg-blue-600 text-white'
                              : 'bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200'
                          }`}
                        >
                          🛵 Pengantaran ({orders.filter((o) => ['menunggu_kurir', 'dijemput', 'siap_diantar', 'diantar'].includes(o.status)).length})
                        </button>
                        <button
                          onClick={() => setOrderFilter('completed')}
                          className={`px-3 py-1.5 rounded-lg font-medium transition-colors ${
                            orderFilter === 'completed'
                              ? 'bg-emerald-600 text-white'
                              : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200'
                          }`}
                        >
                          ✅ Selesai ({orders.filter((o) => ['cod_selesai', 'selesai', 'dibatalkan'].includes(o.status)).length})
                        </button>
                      </div>

                      {/* Orders List */}
                      {ordersLoading ? (
                        <div className="flex items-center justify-center p-8">
                          <div className="w-6 h-6 border-2 border-secondary border-t-transparent rounded-full animate-spin" />
                        </div>
                      ) : filteredOrders.length === 0 ? (
                        <div className="text-center py-12 border border-dashed border-gray-200 rounded-xl bg-gray-50/50">
                          <p className="text-3xl mb-2">🧺</p>
                          <h4 className="text-sm font-semibold text-gray-700">Tidak ada pesanan ditemukan</h4>
                          <p className="text-xs text-gray-400 mt-1">
                            {orderFilter === 'all'
                              ? 'Belum ada pesanan yang masuk untuk toko ini.'
                              : 'Tidak ada pesanan pada kategori filter ini.'}
                          </p>
                        </div>
                      ) : (
                        <div className="space-y-4">
                          {filteredOrders.map((order) => {
                            const isActionLoading = actionLoadingId === order.id;
                            const activeDispute = order.disputes?.find((d) => ['open', 'in_review', 'under_review'].includes(d.status));
                            const resolvedDispute = order.disputes?.find((d) => ['resolved', 'rejected'].includes(d.status));
                            return (
                              <div
                                key={order.id}
                                className="border border-gray-200 rounded-xl p-4 sm:p-5 hover:border-gray-300 transition-all shadow-xs bg-white space-y-4"
                              >
                                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-100 pb-3">
                                  <div>
                                    <div className="flex items-center gap-2">
                                      <span className="font-mono font-bold text-sm text-primary">
                                        {order.nomor_order}
                                      </span>
                                      {getOrderStatusBadge(order.status)}
                                    </div>
                                    <p className="text-xs text-gray-400 mt-0.5">
                                      Dibuat: {new Date(order.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })}
                                    </p>
                                  </div>
                                  <div className="text-xs text-gray-600">
                                    <span className="font-medium text-gray-500">Pemesan: </span>
                                    <span className="font-semibold text-gray-800">{order.customer?.name || 'Pelanggan'}</span>
                                  </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-gray-50/70 p-3 rounded-lg border border-gray-100">
                                  <div>
                                    <p className="text-gray-500 font-medium">Layanan Dipilih:</p>
                                    <p className="font-bold text-gray-800">
                                      {order.tarif?.jenis_layanan || 'Standar'}
                                      <span className="font-normal text-gray-500 ml-1">
                                        (Rp {Number(order.tarif?.harga || 0).toLocaleString('id-ID')} / kg)
                                      </span>
                                    </p>
                                    {order.catatan && (
                                      <p className="text-gray-500 mt-1.5 italic">
                                        "{order.catatan}"
                                      </p>
                                    )}
                                  </div>

                                  <div>
                                    <p className="text-gray-500 font-medium">Berat & Tagihan COD:</p>
                                    <div className="flex items-baseline gap-2 mt-0.5">
                                      <span className="font-bold text-gray-800">
                                        {order.berat ? `${order.berat} kg` : '⚖️ Belum Ditimbang'}
                                      </span>
                                      {order.total_biaya ? (
                                        <span className="text-tertiary-dark font-bold font-mono text-sm">
                                          Rp {Number(order.total_biaya).toLocaleString('id-ID')}
                                        </span>
                                      ) : (
                                        <span className="text-gray-400 text-xs">(Menunggu timbang)</span>
                                      )}
                                    </div>

                                    {order.kurir ? (
                                      <p className="text-xs text-blue-700 mt-1 flex items-center gap-1">
                                        <span>🛵 Kurir:</span>
                                        <span className="font-semibold">{order.kurir.name}</span>
                                      </p>
                                    ) : (
                                      <p className="text-xs text-gray-400 mt-1">
                                        Kurir: Belum ditugaskan
                                      </p>
                                    )}
                                  </div>
                                </div>

                                <div className="text-xs text-gray-600 flex items-start gap-1.5">
                                  <span>📍</span>
                                  <span className="line-clamp-2">
                                    <strong className="text-gray-700">Alamat Customer:</strong> {order.alamat_penjemputan}
                                  </span>
                                </div>

                                {/* Active & Resolved Dispute Banners */}
                                {activeDispute && (
                                  <div className="bg-amber-50 border border-amber-200 p-2.5 rounded-lg flex items-center justify-between text-xs text-amber-900">
                                    <div>
                                      <p className="font-bold">⚠️ Komplain Dispute Aktif: {activeDispute.alasan ? activeDispute.alasan.replace(/_/g, ' ').toUpperCase() : 'DISPUTE'}</p>
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

                                {/* Action Buttons depending on status */}
                                <div className="pt-2 border-t border-gray-100 flex flex-wrap items-center justify-between gap-3">
                                  {!activeDispute && order.status !== 'dibatalkan' && (
                                    <button
                                      type="button"
                                      onClick={() => handleOpenDispute(order)}
                                      className="text-[11px] text-amber-800 bg-amber-50 hover:bg-amber-100 border border-amber-200 font-semibold px-2.5 py-1 rounded-md transition-colors"
                                    >
                                      ⚠️ Laporkan Kendala / Dispute
                                    </button>
                                  )}
                                  {order.status === 'menunggu_konfirmasi' && (
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 w-full">
                                      <p className="text-xs text-amber-700">
                                        ⚠️ Pesanan baru masuk! Konfirmasi untuk menawarkan tugas penjemputan ke kurir.
                                      </p>
                                      <button
                                        onClick={() => handleConfirmOrder(order.id)}
                                        disabled={isActionLoading}
                                        className="bg-primary hover:bg-primary-dark text-white text-xs font-semibold px-4 py-2 rounded-lg transition-colors shadow-xs disabled:opacity-50 self-start sm:self-auto"
                                      >
                                        {isActionLoading ? 'Memproses...' : '✓ Konfirmasi Order'}
                                      </button>
                                    </div>
                                  )}

                                  {order.status === 'menunggu_kurir' && (
                                    <p className="text-xs text-blue-700 bg-blue-50 px-3 py-2 rounded-lg w-full">
                                      🛵 <strong>Menunggu Kurir:</strong> Order telah dikonfirmasi. Menunggu kurir mitra menerima tugas penjemputan.
                                    </p>
                                  )}

                                  {order.status === 'dijemput' && (
                                    <p className="text-xs text-indigo-700 bg-indigo-50 px-3 py-2 rounded-lg w-full">
                                      🚚 <strong>Dalam Perjalanan:</strong> Kurir sedang menjemput pakaian dari customer untuk diantar ke toko Anda.
                                    </p>
                                  )}

                                  {order.status === 'di_toko' && (
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 w-full bg-purple-50 p-3 rounded-lg border border-purple-100">
                                      <div>
                                        <p className="text-xs font-bold text-purple-900">
                                          Pakaian Sudah Tiba di Toko!
                                        </p>
                                        <p className="text-xs text-purple-700">
                                          Segera timbang pakaian dan masukkan berat agar tagihan COD dihitung otomatis.
                                        </p>
                                      </div>
                                      <button
                                        onClick={() => openWeighModal(order)}
                                        className="bg-tertiary hover:bg-tertiary-dark text-white text-xs font-bold px-4 py-2 rounded-lg transition-colors shadow-xs self-start sm:self-auto"
                                      >
                                        ⚖️ Timbang & Hitung Biaya
                                      </button>
                                    </div>
                                  )}

                                  {order.status === 'menunggu_pembayaran_info' && (
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 w-full bg-sky-50 p-3 rounded-lg border border-sky-100">
                                      <div>
                                        <p className="text-xs font-bold text-sky-900">
                                          Tagihan COD: Rp {Number(order.total_biaya).toLocaleString('id-ID')}
                                        </p>
                                        <p className="text-xs text-sky-700">
                                          Info biaya telah dikirim ke customer. Siap untuk mulai dicuci?
                                        </p>
                                      </div>
                                      <button
                                        onClick={() => handleStatusChange(order.id, 'diproses')}
                                        disabled={isActionLoading}
                                        className="bg-primary hover:bg-primary-dark text-white text-xs font-semibold px-4 py-2 rounded-lg transition-colors shadow-xs disabled:opacity-50 self-start sm:self-auto"
                                      >
                                        {isActionLoading ? 'Memproses...' : '🧺 Mulai Cuci (Diproses)'}
                                      </button>
                                    </div>
                                  )}

                                  {order.status === 'diproses' && (
                                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 w-full bg-teal-50 p-3 rounded-lg border border-teal-100">
                                      <div>
                                        <p className="text-xs font-bold text-teal-900">
                                          Pakaian Sedang Dicuci / Disetrika
                                        </p>
                                        <p className="text-xs text-teal-700">
                                          Jika cucian sudah wangi & rapi, tandai selesai untuk dipickup kurir.
                                        </p>
                                      </div>
                                      <button
                                        onClick={() => handleStatusChange(order.id, 'siap_diantar')}
                                        disabled={isActionLoading}
                                        className="bg-secondary hover:bg-secondary-dark text-white text-xs font-semibold px-4 py-2 rounded-lg transition-colors shadow-xs disabled:opacity-50 self-start sm:self-auto"
                                      >
                                        {isActionLoading ? 'Memproses...' : '✨ Selesai Cuci (Siap Diantar)'}
                                      </button>
                                    </div>
                                  )}

                                  {order.status === 'siap_diantar' && (
                                    <p className="text-xs text-cyan-800 bg-cyan-50 px-3 py-2 rounded-lg w-full">
                                      📦 <strong>Siap Diantar:</strong> Cucian sudah selesai diproses. Menunggu kurir mengambil dari toko untuk diantar.
                                    </p>
                                  )}

                                  {order.status === 'diantar' && (
                                    <p className="text-xs text-blue-800 bg-blue-50 px-3 py-2 rounded-lg w-full">
                                      🚀 <strong>Sedang Diantar:</strong> Kurir ({order.kurir?.name || 'Mitra'}) sedang menuju lokasi customer untuk serah terima dan pembayaran COD.
                                    </p>
                                  )}

                                  {(order.status === 'cod_selesai' || order.status === 'selesai') && (
                                    <p className="text-xs text-emerald-800 bg-emerald-50 px-3 py-2 rounded-lg w-full">
                                      🎉 <strong>Pesanan Selesai:</strong> Pembayaran COD tunai sebesar <strong>Rp {Number(order.total_biaya || 0).toLocaleString('id-ID')}</strong> telah berhasil diselesaikan.
                                    </p>
                                  )}

                                  {order.status === 'dibatalkan' && (
                                    <p className="text-xs text-rose-700 bg-rose-50 px-3 py-2 rounded-lg w-full">
                                      ❌ <strong>Pesanan Dibatalkan.</strong>
                                    </p>
                                  )}
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      )}
                    </div>
                  )}

                  {/* TAB 1: TARIF LAYANAN */}
                  {activeTab === 'tarifs' && (
                    <div className="space-y-6">
                      <div className="flex items-center justify-between">
                        <h3 className="font-bold text-gray-900 text-sm">Daftar Tarif Layanan Toko</h3>
                        <span className="text-xs text-gray-400">Scoped to this store</span>
                      </div>

                      {/* List Tarif */}
                      <div className="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden">
                        {selectedToko.tarifs && selectedToko.tarifs.length > 0 ? (
                          selectedToko.tarifs.map((tarif) => (
                            <div key={tarif.id} className="p-3.5 flex items-center justify-between hover:bg-gray-50">
                              <div>
                                <p className="text-sm font-semibold text-gray-800">{tarif.jenis_layanan}</p>
                                <p className="text-xs text-tertiary-dark font-mono font-bold mt-0.5">
                                  Rp {tarif.harga.toLocaleString('id-ID')}
                                </p>
                              </div>
                              <button
                                onClick={() => handleDeleteTarif(tarif.id)}
                                className="text-xs text-red-600 hover:text-red-800 hover:bg-red-50 px-2.5 py-1 rounded transition-colors"
                              >
                                Hapus
                              </button>
                            </div>
                          ))
                        ) : (
                          <div className="p-6 text-center text-sm text-gray-400">
                            Belum ada tarif layanan. Tambahkan jenis layanan di bawah.
                          </div>
                        )}
                      </div>

                      {/* Form Tambah Tarif */}
                      <form onSubmit={handleAddTarif} className="bg-neutral p-4 rounded-lg border border-gray-200 space-y-3">
                        <h4 className="text-xs font-bold text-gray-700 uppercase">Tambah Jenis Layanan Baru</h4>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                          <input
                            type="text"
                            required
                            placeholder="Jenis layanan (e.g. Cuci Komplit, Bed Cover)"
                            value={newTarif.jenis_layanan}
                            onChange={(e) => setNewTarif({ ...newTarif, jenis_layanan: e.target.value })}
                            className="bg-white border border-gray-300 rounded-lg p-2 text-xs"
                          />
                          <input
                            type="number"
                            required
                            min="0"
                            placeholder="Harga (Rp)"
                            value={newTarif.harga}
                            onChange={(e) => setNewTarif({ ...newTarif, harga: e.target.value })}
                            className="bg-white border border-gray-300 rounded-lg p-2 text-xs"
                          />
                        </div>
                        <button
                          type="submit"
                          disabled={submittingTarif}
                          className="w-full bg-secondary hover:bg-secondary-dark text-white text-xs font-semibold py-2 rounded-lg transition-colors disabled:opacity-50"
                        >
                          {submittingTarif ? 'Menyimpan...' : 'Tambah Tarif'}
                        </button>
                      </form>
                    </div>
                  )}

                  {/* TAB 2: STAFF ADMIN TOKO */}
                  {activeTab === 'staff' && (
                    <div className="space-y-6">
                      <div className="flex items-center justify-between">
                        <div>
                          <h3 className="font-bold text-gray-900 text-sm">Staff Admin Toko (Scoped Access)</h3>
                          <p className="text-xs text-gray-500">
                            Admin toko hanya bisa mengelola toko ini saja (Defense in Depth: Route Middleware + Policy).
                          </p>
                        </div>
                      </div>

                      {/* List Staff */}
                      <div className="divide-y divide-gray-100 border border-gray-200 rounded-lg overflow-hidden">
                        {selectedToko.staff_users && selectedToko.staff_users.length > 0 ? (
                          selectedToko.staff_users.map((staffUser) => (
                            <div key={staffUser.id} className="p-3.5 flex items-center justify-between hover:bg-gray-50">
                              <div className="flex items-center gap-3">
                                <div className="w-8 h-8 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs">
                                  {staffUser.name?.charAt(0) || 'A'}
                                </div>
                                <div>
                                  <p className="text-sm font-semibold text-gray-800">{staffUser.name}</p>
                                  <p className="text-xs text-gray-500">{staffUser.email}</p>
                                </div>
                              </div>
                              {isStoreOwner && (
                                <button
                                  onClick={() => handleRemoveStaff(staffUser.pivot?.id || staffUser.id)}
                                  className="text-xs text-red-600 hover:text-red-800 hover:bg-red-50 px-2.5 py-1 rounded transition-colors"
                                >
                                  Cabut Akses
                                </button>
                              )}
                            </div>
                          ))
                        ) : (
                          <div className="p-6 text-center text-sm text-gray-400">
                            Belum ada Admin Toko yang ditugaskan pada toko ini.
                          </div>
                        )}
                      </div>

                      {/* Form Invite Staff - Hanya untuk Owner Toko */}
                      {isStoreOwner ? (
                        <form onSubmit={handleAddStaff} className="bg-neutral p-4 rounded-lg border border-gray-200 space-y-3">
                          <h4 className="text-xs font-bold text-gray-700 uppercase">Tugaskan Admin Toko Baru</h4>
                          <p className="text-xs text-gray-500">
                            Masukkan email user yang sudah terdaftar di Pusat Laundry. Role <code>admin_toko</code> akan otomatis di-assign ke user tersebut untuk toko ini.
                          </p>
                          <div className="flex gap-2">
                            <input
                              type="email"
                              required
                              placeholder="email.user@gmail.com"
                              value={newStaffEmail}
                              onChange={(e) => setNewStaffEmail(e.target.value)}
                              className="flex-1 bg-white border border-gray-300 rounded-lg p-2 text-xs"
                            />
                            <button
                              type="submit"
                              disabled={submittingStaff}
                              className="bg-primary hover:bg-primary-dark text-white text-xs font-semibold px-4 py-2 rounded-lg transition-colors disabled:opacity-50"
                            >
                              {submittingStaff ? 'Menugaskan...' : 'Tugaskan Staff'}
                            </button>
                          </div>
                        </form>
                      ) : (
                        <div className="bg-amber-50 text-amber-800 p-3 rounded-lg text-xs">
                          ℹ️ Anda bertindak sebagai Admin Toko. Hanya pemilik utama (Owner Toko) yang berhak mengundang atau mencabut akses staff.
                        </div>
                      )}
                    </div>
                  )}

                  {/* TAB 3: SETTLEMENT & PAYOUT */}
                  {activeTab === 'settlement' && (
                    <div className="space-y-6">
                      <div className="flex items-center justify-between">
                        <div>
                          <h3 className="font-bold text-gray-900 text-sm">Penghasilan & Riwayat Settlement</h3>
                          <p className="text-xs text-gray-500">
                            Rekapitulasi penjualan bersih toko setelah potongan komisi platform (10%).
                          </p>
                        </div>
                        <button
                          onClick={() => fetchSettlement(selectedToko.id)}
                          className="text-xs text-secondary hover:text-secondary-dark font-medium cursor-pointer"
                        >
                          🔄 Refresh
                        </button>
                      </div>

                      {settlementLoading ? (
                        <div className="py-8 text-center text-xs text-gray-400">Memuat data settlement...</div>
                      ) : !settlementData ? (
                        <div className="py-8 text-center text-xs text-gray-400">Data settlement belum tersedia.</div>
                      ) : (
                        <div className="space-y-5">
                          {/* 4 Metrics Cards */}
                          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                            <div className="bg-neutral p-4 rounded-xl border border-gray-200">
                              <p className="text-gray-500 font-semibold">Total Order Selesai</p>
                              <p className="text-xl font-bold font-mono text-gray-800 mt-1">
                                {settlementData.settlement_summary?.total_completed_orders || 0}
                              </p>
                            </div>
                            <div className="bg-neutral p-4 rounded-xl border border-gray-200">
                              <p className="text-gray-500 font-semibold">Omset Kotor</p>
                              <p className="text-xl font-bold font-mono text-gray-800 mt-1">
                                Rp {Number(settlementData.settlement_summary?.gross_revenue || 0).toLocaleString('id-ID')}
                              </p>
                            </div>
                            <div className="bg-rose-50 p-4 rounded-xl border border-rose-200 text-rose-800">
                              <p className="font-semibold">Fee Platform (10%)</p>
                              <p className="text-xl font-bold font-mono mt-1">
                                - Rp {Number(settlementData.settlement_summary?.platform_fee_amount || 0).toLocaleString('id-ID')}
                              </p>
                            </div>
                            <div className="bg-emerald-50 p-4 rounded-xl border border-emerald-200 text-emerald-800">
                              <p className="font-semibold">Hak Saldo Bersih Toko (90%)</p>
                              <p className="text-xl font-black font-mono mt-1">
                                Rp {Number(settlementData.settlement_summary?.net_earnings || 0).toLocaleString('id-ID')}
                              </p>
                            </div>
                          </div>

                          {/* Payout Threshold Notice */}
                          <div className="bg-blue-50 border border-blue-200 p-3.5 rounded-xl text-xs text-blue-800 flex items-center justify-between">
                            <div>
                              <p className="font-bold">Status Pencairan / Payout:</p>
                              <p className="mt-0.5">
                                Batas minimum pencairan dana adalah <strong>Rp 50.000</strong>.
                                {settlementData.settlement_summary?.can_request_payout ? (
                                  <span className="text-emerald-700 font-bold ml-1">✓ Saldo memenuhi syarat untuk settlement otomatis platform.</span>
                                ) : (
                                  <span className="text-amber-700 font-medium ml-1">Saldo belum mencapai batas minimum.</span>
                                )}
                              </p>
                            </div>
                            <span className={`px-2.5 py-1 rounded-full text-[10px] font-bold ${settlementData.settlement_summary?.can_request_payout ? 'bg-emerald-200 text-emerald-900' : 'bg-amber-200 text-amber-900'}`}>
                              {settlementData.settlement_summary?.can_request_payout ? 'Siap Cair' : 'Menunggu Akumulasi'}
                            </span>
                          </div>

                          {/* Transactions Table */}
                          <div className="border border-gray-200 rounded-xl overflow-hidden">
                            <div className="p-3 bg-neutral border-b border-gray-200 text-xs font-bold text-gray-700">
                              Riwayat Transaksi Order Selesai
                            </div>
                            <div className="overflow-x-auto">
                              <table className="w-full text-left text-xs">
                                <thead className="bg-gray-50 text-gray-500 font-semibold border-b border-gray-200">
                                  <tr>
                                    <th className="py-2.5 px-3">Nomor Order</th>
                                    <th className="py-2.5 px-3">Customer</th>
                                    <th className="py-2.5 px-3">Layanan</th>
                                    <th className="py-2.5 px-3">Tanggal Selesai</th>
                                    <th className="py-2.5 px-3 text-right">Total Transaksi COD</th>
                                  </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100">
                                  {settlementData.transactions?.data?.length === 0 ? (
                                    <tr>
                                      <td colSpan={5} className="py-6 text-center text-gray-400">Belum ada order selesai.</td>
                                    </tr>
                                  ) : (
                                    settlementData.transactions?.data?.map((tx) => (
                                      <tr key={tx.id} className="hover:bg-gray-50">
                                        <td className="py-2.5 px-3 font-mono font-bold text-primary">{tx.nomor_order}</td>
                                        <td className="py-2.5 px-3 font-medium text-gray-800">{tx.customer?.name}</td>
                                        <td className="py-2.5 px-3 text-gray-600">{tx.tarif?.jenis_layanan}</td>
                                        <td className="py-2.5 px-3 text-gray-500">
                                          {new Date(tx.updated_at).toLocaleDateString('id-ID', { dateStyle: 'medium' })}
                                        </td>
                                        <td className="py-2.5 px-3 text-right font-mono font-bold text-emerald-700">
                                          Rp {Number(tx.total_biaya || 0).toLocaleString('id-ID')}
                                        </td>
                                      </tr>
                                    ))
                                  )}
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      )}
                    </div>
                  )}

                  {/* TAB 4: PROFIL TOKO */}
                  {activeTab === 'profile' && (
                    <div className="space-y-6 max-w-xl">
                      <div>
                        <h3 className="font-bold text-gray-900 text-sm">Pengaturan Profil Toko</h3>
                        <p className="text-xs text-gray-500">
                          Perbarui identitas toko laundry Anda yang tampil di marketplace publik.
                        </p>
                      </div>

                      {profileSaved && (
                        <div className="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold">
                          ✓ Profil toko berhasil diperbarui!
                        </div>
                      )}

                      <form onSubmit={handleSaveProfile} className="space-y-4 text-xs">
                        <div>
                          <label className="block font-bold text-gray-700 mb-1">Nama Toko Laundry:</label>
                          <input
                            type="text"
                            required
                            value={profileForm.nama}
                            onChange={(e) => setProfileForm({ ...profileForm, nama: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-lg p-2.5 text-xs outline-none focus:ring-1 focus:ring-primary font-semibold"
                          />
                        </div>

                        <div>
                          <label className="block font-bold text-gray-700 mb-1">Alamat Lengkap Toko:</label>
                          <textarea
                            rows={3}
                            required
                            value={profileForm.alamat}
                            onChange={(e) => setProfileForm({ ...profileForm, alamat: e.target.value })}
                            className="w-full bg-white border border-gray-300 rounded-lg p-2.5 text-xs outline-none focus:ring-1 focus:ring-primary"
                          />
                        </div>

                        <button
                          type="submit"
                          disabled={savingProfile}
                          className="bg-primary hover:bg-primary-dark text-white font-bold px-5 py-2.5 rounded-xl transition-colors disabled:opacity-50 cursor-pointer shadow-xs"
                        >
                          {savingProfile ? 'Menyimpan...' : 'Simpan Perubahan'}
                        </button>
                      </form>
                    </div>
                  )}
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Modal Input Berat & Hitung Biaya */}
      {weighModalOpen && orderToWeigh && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-5">
            <div className="flex items-start justify-between">
              <div>
                <h3 className="text-lg font-bold text-primary flex items-center gap-2">
                  <span>⚖️</span> Timbang Pakaian & Hitung Biaya
                </h3>
                <p className="text-xs text-gray-500 mt-0.5">
                  Order <strong className="font-mono text-gray-700">{orderToWeigh.nomor_order}</strong>
                </p>
              </div>
              <button
                onClick={() => {
                  setWeighModalOpen(false);
                  setOrderToWeigh(null);
                }}
                className="text-gray-400 hover:text-gray-600 text-lg leading-none p-1"
              >
                ✕
              </button>
            </div>

            <div className="bg-gray-50 p-3.5 rounded-xl border border-gray-100 text-xs space-y-2">
              <div className="flex justify-between">
                <span className="text-gray-500">Customer:</span>
                <span className="font-semibold text-gray-800">{orderToWeigh.customer?.name}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-500">Layanan:</span>
                <span className="font-semibold text-gray-800">{orderToWeigh.tarif?.jenis_layanan}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-gray-500">Tarif per Kg:</span>
                <span className="font-mono font-bold text-gray-800">
                  Rp {Number(orderToWeigh.tarif?.harga || 0).toLocaleString('id-ID')} / kg
                </span>
              </div>
            </div>

            <form onSubmit={handleWeighSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Berat Cucian Aktual (kg) <span className="text-red-500">*</span>
                </label>
                <div className="relative">
                  <input
                    type="number"
                    step="0.01"
                    min="0.01"
                    required
                    placeholder="Contoh: 3.5"
                    value={inputBerat}
                    onChange={(e) => setInputBerat(e.target.value)}
                    className="w-full bg-white border border-gray-300 rounded-lg p-3 text-sm font-semibold focus:ring-2 focus:ring-secondary focus:border-transparent outline-none"
                    autoFocus
                  />
                  <span className="absolute right-3.5 top-3.5 text-xs font-bold text-gray-400">
                    KG
                  </span>
                </div>
                <p className="text-[11px] text-gray-400 mt-1">
                  Gunakan timbangan digital toko untuk hasil presisi hingga 2 desimal.
                </p>
              </div>

              {/* Live Price Calculation Preview */}
              <div className="bg-tertiary/10 border border-tertiary/30 rounded-xl p-4">
                <p className="text-xs text-tertiary-dark font-medium">Total Tagihan COD Otomatis:</p>
                <p className="text-2xl font-black font-mono text-tertiary-dark mt-1">
                  Rp{' '}
                  {(
                    (parseFloat(inputBerat) || 0) * (orderToWeigh.tarif?.harga || 0)
                  ).toLocaleString('id-ID')}
                </p>
                <p className="text-[11px] text-gray-500 mt-1">
                  💡 Nominal ini akan otomatis muncul pada status pemesanan customer untuk ditagih tunai (COD) oleh kurir saat pengantaran.
                </p>
              </div>

              <div className="flex gap-3 pt-2">
                <button
                  type="button"
                  onClick={() => {
                    setWeighModalOpen(false);
                    setOrderToWeigh(null);
                  }}
                  className="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold py-2.5 rounded-lg transition-colors"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={submittingWeigh || !inputBerat || parseFloat(inputBerat) <= 0}
                  className="flex-1 bg-tertiary hover:bg-tertiary-dark text-white text-xs font-bold py-2.5 rounded-lg transition-colors disabled:opacity-50 shadow-sm"
                >
                  {submittingWeigh ? 'Menyimpan...' : '✓ Simpan & Kirim Info Biaya'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Dispute Modal Toko */}
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
                  Alasan Masalah:
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
                  placeholder="Jelaskan kondisi cucian atau kendala yang dihadapi oleh pihak toko..."
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
      </div>
    </DashboardLayout>
  );
}
