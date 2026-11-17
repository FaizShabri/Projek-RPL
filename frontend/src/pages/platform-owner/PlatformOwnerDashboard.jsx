import { useState, useEffect } from 'react';
import api from '../../api/client';
import DashboardLayout from '../../components/dashboard/DashboardLayout';
import StatCard from '../../components/dashboard/StatCard';
import StatusBadge from '../../components/dashboard/StatusBadge';
import AccountManagement from '../management/AccountManagement';

export default function PlatformOwnerDashboard() {
  const [activeTab, setActiveTab] = useState('overview'); // 'overview' | 'commission' | 'settlements' | 'audit_logs'
  const [overview, setOverview] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Commission Settings state
  const [commissionRate, setCommissionRate] = useState(10);
  const [minSettlement, setMinSettlement] = useState(50000);
  const [savingCommission, setSavingCommission] = useState(false);
  const [commissionSuccess, setCommissionSuccess] = useState(false);

  // Settlements state
  const [settlements, setSettlements] = useState([]);
  const [settlementSummary, setSettlementSummary] = useState(null);
  const [processingPayoutId, setProcessingPayoutId] = useState(null);

  // Audit Logs state
  const [auditLogs, setAuditLogs] = useState([]);
  const [selectedAuditLog, setSelectedAuditLog] = useState(null);

  // Pending Accounts state
  const [pendingAccountsCount, setPendingAccountsCount] = useState(0);

  const fetchPendingAccountsCount = async () => {
    try {
      const res = await api.get('/management/pending-accounts/count');
      setPendingAccountsCount(res.count || 0);
    } catch (err) {
      console.error('Error fetching pending accounts count:', err);
    }
  };

  const fetchOverview = async () => {
    try {
      setLoading(true);
      const res = await api.get('/platform/overview');
      setOverview(res.overview || null);
      if (res.overview?.commission_rate_percentage) {
        setCommissionRate(res.overview.commission_rate_percentage);
      }
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  const fetchSettlements = async () => {
    try {
      const res = await api.get('/platform/finances/settlements');
      setSettlements(res.settlements || []);
      setSettlementSummary(res.summary || null);
    } catch (err) {
      console.error('Error fetching settlements:', err);
    }
  };

  const fetchAuditLogs = async () => {
    try {
      const res = await api.get('/platform/audit-logs');
      setAuditLogs(res.data || []);
    } catch (err) {
      console.error('Error fetching audit logs:', err);
    }
  };

  useEffect(() => {
    fetchOverview();
    fetchPendingAccountsCount();
  }, []);

  useEffect(() => {
    if (activeTab === 'settlements') fetchSettlements();
    if (activeTab === 'audit_logs') fetchAuditLogs();
  }, [activeTab]);

  const handleSaveCommission = async (e) => {
    e.preventDefault();
    setSavingCommission(true);
    setCommissionSuccess(false);
    try {
      await api.put('/platform/finances/commission', {
        commission_rate_percentage: parseFloat(commissionRate),
        min_settlement_amount: parseFloat(minSettlement),
      });
      setCommissionSuccess(true);
      fetchOverview();
    } catch (err) {
      alert('Gagal menyimpan komisi: ' + err.message);
    } finally {
      setSavingCommission(false);
    }
  };

  const handleProcessPayout = async (tokoId, namaToko, amount) => {
    if (!confirm(`Konfirmasi pencairan settlement sebesar Rp ${Number(amount).toLocaleString('id-ID')} ke toko ${namaToko}?`)) {
      return;
    }

    setProcessingPayoutId(tokoId);
    try {
      const res = await api.post(`/platform/finances/settlements/${tokoId}/payout`);
      alert(res.message);
      fetchSettlements();
    } catch (err) {
      alert('Gagal proses payout: ' + err.message);
    } finally {
      setProcessingPayoutId(null);
    }
  };

  const navItems = [
    { label: 'Overview Sistem', icon: '📊', path: '#overview', onClick: () => setActiveTab('overview') },
    {
      label: pendingAccountsCount > 0 ? `Manajemen Akun (${pendingAccountsCount})` : 'Manajemen Akun',
      icon: '👥',
      path: '#users',
      onClick: () => setActiveTab('users'),
    },
    { label: 'Pengaturan Komisi', icon: '⚙️', path: '#commission', onClick: () => setActiveTab('commission') },
    { label: 'Laporan Keuangan & Settlement', icon: '💰', path: '#settlements', onClick: () => setActiveTab('settlements') },
    { label: 'Audit Log Lengkap', icon: '📜', path: '#audit_logs', onClick: () => setActiveTab('audit_logs') },
  ];

  return (
    <DashboardLayout
      requiredRole="platform_owner"
      title="Platform Owner Dashboard"
      subtitle="Overview sistem, kebijakan komisi, laporan keuangan seluruh toko & audit log."
      badgeIcon="👑"
      navItems={navItems.map((item) => ({
        ...item,
        path: '#',
        icon: item.icon,
        label: item.label,
        end: true,
      }))}
    >
      {/* Tab Switcher Bar */}
      <div className="flex border-b border-gray-200 bg-white rounded-2xl p-1.5 shadow-xs overflow-x-auto gap-1">
        <button
          onClick={() => setActiveTab('overview')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer ${
            activeTab === 'overview'
              ? 'bg-primary text-white shadow-xs'
              : 'text-gray-600 hover:text-primary hover:bg-gray-100'
          }`}
        >
          📊 Overview Sistem
        </button>
        <button
          onClick={() => setActiveTab('users')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer flex items-center gap-1.5 ${
            activeTab === 'users'
              ? 'bg-primary text-white shadow-xs'
              : 'text-gray-600 hover:text-primary hover:bg-gray-100'
          }`}
        >
          <span>👥 Manajemen Akun</span>
          {pendingAccountsCount > 0 && (
            <span className="bg-amber-400 text-slate-900 text-[10px] font-black px-1.5 py-0.5 rounded-full">
              {pendingAccountsCount}
            </span>
          )}
        </button>
        <button
          onClick={() => setActiveTab('commission')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer ${
            activeTab === 'commission'
              ? 'bg-primary text-white shadow-xs'
              : 'text-gray-600 hover:text-primary hover:bg-gray-100'
          }`}
        >
          ⚙️ Pengaturan Komisi
        </button>
        <button
          onClick={() => setActiveTab('settlements')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer ${
            activeTab === 'settlements'
              ? 'bg-primary text-white shadow-xs'
              : 'text-gray-600 hover:text-primary hover:bg-gray-100'
          }`}
        >
          💰 Laporan Keuangan & Settlement
        </button>
        <button
          onClick={() => setActiveTab('audit_logs')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer ${
            activeTab === 'audit_logs'
              ? 'bg-primary text-white shadow-xs'
              : 'text-gray-600 hover:text-primary hover:bg-gray-100'
          }`}
        >
          📜 Audit Log Lengkap
        </button>
      </div>

      {loading ? (
        <div className="p-12 text-center bg-white rounded-2xl border border-gray-200 shadow-xs">
          <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto mb-2" />
          <p className="text-xs text-gray-500">Memuat data platform...</p>
        </div>
      ) : error ? (
        <div className="p-4 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700">
          ⚠️ {error}
        </div>
      ) : (
        <>
          {/* TAB: MANAJEMEN AKUN */}
          {activeTab === 'users' && (
            <AccountManagement />
          )}

          {/* TAB 1: OVERVIEW SISTEM */}
          {activeTab === 'overview' && overview && (
            <div className="space-y-6">
              {/* Stat Cards Grid */}
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard
                  title="Total GMV (Omset)"
                  value={`Rp ${Number(overview.total_gmv || 0).toLocaleString('id-ID')}`}
                  icon="💵"
                  variant="tertiary"
                  subtitle={`${overview.completed_orders || 0} order selesai`}
                />
                <StatCard
                  title="Komisi Platform (10%)"
                  value={`Rp ${Number(overview.total_commission || 0).toLocaleString('id-ID')}`}
                  icon="🏦"
                  variant="secondary"
                  subtitle="Pendapatan bersih platform"
                />
                <StatCard
                  title="Total Toko Mitra"
                  value={overview.total_tokos || 0}
                  icon="🏪"
                  variant="primary"
                  subtitle={`${overview.tokos_by_status?.approved || 0} approved • ${overview.tokos_by_status?.submitted || 0} pending`}
                />
                <StatCard
                  title="Total Pengguna"
                  value={overview.total_users || 0}
                  icon="👥"
                  variant="purple"
                  subtitle="Pelanggan, Kurir, Mitra & Admin"
                />
              </div>

              {/* Status Breakdown Section */}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* Toko Status Distribution */}
                <div className="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                  <h3 className="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <span>🏪</span> Distribusi Status Toko Mitra
                  </h3>
                  <div className="space-y-2 text-xs">
                    <div className="flex justify-between items-center p-2 rounded-lg bg-emerald-50 text-emerald-800">
                      <span className="font-semibold">Approved (Aktif Melayani)</span>
                      <span className="font-mono font-bold text-sm">{overview.tokos_by_status?.approved || 0}</span>
                    </div>
                    <div className="flex justify-between items-center p-2 rounded-lg bg-amber-50 text-amber-800">
                      <span className="font-semibold">Submitted (Menunggu Verifikasi)</span>
                      <span className="font-mono font-bold text-sm">{overview.tokos_by_status?.submitted || 0}</span>
                    </div>
                    <div className="flex justify-between items-center p-2 rounded-lg bg-rose-50 text-rose-800">
                      <span className="font-semibold">Rejected (Pengajuan Ditolak)</span>
                      <span className="font-mono font-bold text-sm">{overview.tokos_by_status?.rejected || 0}</span>
                    </div>
                    <div className="flex justify-between items-center p-2 rounded-lg bg-gray-100 text-gray-800">
                      <span className="font-semibold">Suspended (Dinonaktifkan)</span>
                      <span className="font-mono font-bold text-sm">{overview.tokos_by_status?.suspended || 0}</span>
                    </div>
                  </div>
                </div>

                {/* Pesanan Metrics */}
                <div className="bg-white p-5 rounded-2xl border border-gray-200 shadow-xs space-y-3">
                  <h3 className="text-sm font-bold text-gray-900 flex items-center gap-2">
                    <span>📦</span> Metrik Perputaran Pesanan
                  </h3>
                  <div className="space-y-2 text-xs">
                    <div className="flex justify-between items-center p-2 rounded-lg bg-neutral border border-gray-100">
                      <span className="text-gray-600">Total Akumulasi Order</span>
                      <span className="font-mono font-bold text-gray-800">{overview.total_orders || 0}</span>
                    </div>
                    <div className="flex justify-between items-center p-2 rounded-lg bg-blue-50 text-blue-800">
                      <span className="font-semibold">Order Sedang Aktif / Berjalan</span>
                      <span className="font-mono font-bold text-sm">{overview.active_orders || 0}</span>
                    </div>
                    <div className="flex justify-between items-center p-2 rounded-lg bg-emerald-50 text-emerald-800">
                      <span className="font-semibold">Order Selesai (Lunas COD)</span>
                      <span className="font-mono font-bold text-sm">{overview.completed_orders || 0}</span>
                    </div>
                    <div className="flex justify-between items-center p-2 rounded-lg bg-purple-50 text-purple-800">
                      <span className="font-semibold">Hak Payout Semua Mitra (90%)</span>
                      <span className="font-mono font-bold text-sm">
                        Rp {Number(overview.total_store_payouts || 0).toLocaleString('id-ID')}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          )}

          {/* TAB 2: PENGATURAN KOMISI MARKETPLACE */}
          {activeTab === 'commission' && (
            <div className="bg-white p-6 rounded-2xl border border-gray-200 shadow-xs max-w-2xl space-y-5">
              <div>
                <h3 className="text-base font-bold text-gray-900 flex items-center gap-2">
                  <span>⚙️</span> Kebijakan Komisi & Keuangan Inti Platform
                </h3>
                <p className="text-xs text-gray-500 mt-1">
                  Pengaturan ini menentukan potongan persentase komisi platform dari setiap transaksi order laundry yang selesai.
                  <strong> Hanya Platform Owner yang berhak mengubah nilai ini.</strong>
                </p>
              </div>

              {commissionSuccess && (
                <div className="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold">
                  ✓ Pengaturan komisi dan threshold berhasil diperbarui dan tercatat di audit log.
                </div>
              )}

              <form onSubmit={handleSaveCommission} className="space-y-4 text-xs">
                <div>
                  <label className="block font-bold text-gray-700 mb-1">
                    Persentase Komisi Platform (%)
                  </label>
                  <div className="relative max-w-xs">
                    <input
                      type="number"
                      step="0.1"
                      min="0"
                      max="100"
                      required
                      value={commissionRate}
                      onChange={(e) => setCommissionRate(e.target.value)}
                      className="w-full bg-white border border-gray-300 rounded-lg p-2.5 text-sm font-bold font-mono focus:ring-2 focus:ring-secondary focus:border-transparent outline-none"
                    />
                    <span className="absolute right-3 top-2.5 font-bold text-gray-400">%</span>
                  </div>
                  <p className="text-[11px] text-gray-400 mt-1">
                    Contoh: 10% berarti dari order Rp 50.000, komisi platform adalah Rp 5.000 dan saldo toko adalah Rp 45.000.
                  </p>
                </div>

                <div>
                  <label className="block font-bold text-gray-700 mb-1">
                    Batas Minimum Pencairan / Settlement (Rp)
                  </label>
                  <div className="relative max-w-xs">
                    <input
                      type="number"
                      min="0"
                      step="10000"
                      required
                      value={minSettlement}
                      onChange={(e) => setMinSettlement(e.target.value)}
                      className="w-full bg-white border border-gray-300 rounded-lg p-2.5 text-sm font-bold font-mono focus:ring-2 focus:ring-secondary focus:border-transparent outline-none"
                    />
                  </div>
                  <p className="text-[11px] text-gray-400 mt-1">
                    Saldo bersih toko harus mencapai ambang batas ini sebelum tombol pencairan dapat diproses.
                  </p>
                </div>

                <div className="bg-neutral p-3.5 rounded-xl border border-gray-200 space-y-1">
                  <p className="font-bold text-gray-700">Metode Arus Kas:</p>
                  <p className="text-gray-500">
                    Sistem saat ini menerapkan <strong>Cash on Delivery (COD) / Tunai</strong>. Kurir mengumpulkan uang tunai dari pelanggan, lalu toko menyetor selisih komisi platform secara periodik melalui rekonsiliasi settlement.
                  </p>
                </div>

                <button
                  type="submit"
                  disabled={savingCommission}
                  className="bg-primary hover:bg-primary-dark text-white font-bold px-5 py-2.5 rounded-xl transition-colors disabled:opacity-50 shadow-xs cursor-pointer"
                >
                  {savingCommission ? 'Menyimpan...' : 'Simpan Pengaturan Komisi'}
                </button>
              </form>
            </div>
          )}

          {/* TAB 3: LAPORAN KEUANGAN & SETTLEMENT */}
          {activeTab === 'settlements' && (
            <div className="space-y-6">
              {settlementSummary && (
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                  <StatCard
                    title="Total Omset Seluruh Toko"
                    value={`Rp ${Number(settlementSummary.total_gross_all || 0).toLocaleString('id-ID')}`}
                    icon="🧺"
                    variant="primary"
                  />
                  <StatCard
                    title="Total Komisi Platform (10%)"
                    value={`Rp ${Number(settlementSummary.total_commission_all || 0).toLocaleString('id-ID')}`}
                    icon="🏦"
                    variant="tertiary"
                  />
                  <StatCard
                    title="Total Hak Bersih Mitra Toko"
                    value={`Rp ${Number(settlementSummary.total_payable_all || 0).toLocaleString('id-ID')}`}
                    icon="💳"
                    variant="purple"
                  />
                </div>
              )}

              {/* Table Settlement */}
              <div className="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden">
                <div className="p-4 border-b border-gray-100 flex items-center justify-between">
                  <h3 className="font-bold text-sm text-gray-900">Rekapitulasi Saldo & Payout Tiap Toko Mitra</h3>
                  <button
                    onClick={fetchSettlements}
                    className="text-xs text-secondary hover:text-secondary-dark font-medium flex items-center gap-1"
                  >
                    🔄 Segarkan Data
                  </button>
                </div>

                <div className="overflow-x-auto">
                  <table className="w-full text-left text-xs">
                    <thead className="bg-neutral text-gray-500 border-b border-gray-200 font-semibold uppercase">
                      <tr>
                        <th className="py-3 px-4">Toko Laundry</th>
                        <th className="py-3 px-4">Pemilik (Owner)</th>
                        <th className="py-3 px-4 text-center">Order Selesai</th>
                        <th className="py-3 px-4 text-right">Omset Kotor</th>
                        <th className="py-3 px-4 text-right">Fee Platform (10%)</th>
                        <th className="py-3 px-4 text-right">Saldo Bersih Toko</th>
                        <th className="py-3 px-4 text-center">Status Payout</th>
                        <th className="py-3 px-4 text-center">Aksi</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100">
                      {settlements.length === 0 ? (
                        <tr>
                          <td colSpan={8} className="py-8 text-center text-gray-400">
                            Belum ada transaksi selesai pada toko mitra.
                          </td>
                        </tr>
                      ) : (
                        settlements.map((item) => (
                          <tr key={item.toko_id} className="hover:bg-gray-50/80 transition-colors">
                            <td className="py-3.5 px-4">
                              <p className="font-bold text-gray-900">{item.nama_toko}</p>
                              <StatusBadge status={item.status_toko} />
                            </td>
                            <td className="py-3.5 px-4 text-gray-600">
                              <p className="font-medium text-gray-800">{item.owner_name || '-'}</p>
                              <p className="text-[10px] text-gray-400">{item.owner_email}</p>
                            </td>
                            <td className="py-3.5 px-4 text-center font-mono font-semibold">
                              {item.completed_orders_count}
                            </td>
                            <td className="py-3.5 px-4 text-right font-mono font-medium text-gray-700">
                              Rp {Number(item.gross_sales).toLocaleString('id-ID')}
                            </td>
                            <td className="py-3.5 px-4 text-right font-mono text-rose-600">
                              - Rp {Number(item.platform_fee_amount).toLocaleString('id-ID')}
                            </td>
                            <td className="py-3.5 px-4 text-right font-mono font-bold text-tertiary-dark text-sm">
                              Rp {Number(item.net_payout).toLocaleString('id-ID')}
                            </td>
                            <td className="py-3.5 px-4 text-center">
                              <StatusBadge status={item.settlement_status} />
                            </td>
                            <td className="py-3.5 px-4 text-center">
                              <button
                                onClick={() => handleProcessPayout(item.toko_id, item.nama_toko, item.net_payout)}
                                disabled={processingPayoutId === item.toko_id || item.net_payout < 50000}
                                className="bg-secondary hover:bg-secondary-dark text-white text-[11px] font-bold px-3 py-1.5 rounded-lg transition-colors disabled:opacity-40 cursor-pointer shadow-xs"
                              >
                                {processingPayoutId === item.toko_id ? 'Proses...' : 'Cairkan Payout'}
                              </button>
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

          {/* TAB 4: AUDIT LOG LENGKAP */}
          {activeTab === 'audit_logs' && (
            <div className="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden space-y-4 p-5">
              <div className="flex items-center justify-between">
                <div>
                  <h3 className="font-bold text-sm text-gray-900 flex items-center gap-2">
                    <span>📜</span> Log Audit Sistem (Immutable)
                  </h3>
                  <p className="text-xs text-gray-500">
                    Catatan seluruh tindakan penting platform yang tersimpan permanen dan tidak dapat dimanipulasi/dihapus.
                  </p>
                </div>
                <button
                  onClick={fetchAuditLogs}
                  className="text-xs text-secondary hover:text-secondary-dark font-medium"
                >
                  🔄 Refresh
                </button>
              </div>

              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead className="bg-neutral text-gray-500 border-b border-gray-200 font-semibold uppercase">
                    <tr>
                      <th className="py-2.5 px-3">Waktu</th>
                      <th className="py-2.5 px-3">Pelaku (Actor)</th>
                      <th className="py-2.5 px-3">Tindakan (Action)</th>
                      <th className="py-2.5 px-3">Target Entitas</th>
                      <th className="py-2.5 px-3 text-center">Detail Perubahan</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-100 font-mono">
                    {auditLogs.length === 0 ? (
                      <tr>
                        <td colSpan={5} className="py-8 text-center text-gray-400 font-sans">
                          Belum ada audit log yang tercatat.
                        </td>
                      </tr>
                    ) : (
                      auditLogs.map((log) => (
                        <tr key={log.id} className="hover:bg-gray-50">
                          <td className="py-3 px-3 text-gray-500 whitespace-nowrap">
                            {new Date(log.created_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'medium' })}
                          </td>
                          <td className="py-3 px-3 font-sans">
                            <span className="font-bold text-gray-800">{log.actor?.name || 'Sistem Otomatis'}</span>
                            <span className="text-[10px] text-gray-400 block">{log.actor?.email}</span>
                          </td>
                          <td className="py-3 px-3">
                            <span className="px-2 py-0.5 rounded-md bg-primary/10 text-primary font-bold text-[11px]">
                              {log.action}
                            </span>
                          </td>
                          <td className="py-3 px-3 text-gray-600 text-[11px]">
                            {log.target_type ? `${log.target_type.split('\\').pop()} #${log.target_id || ''}` : '-'}
                          </td>
                          <td className="py-3 px-3 text-center font-sans">
                            <button
                              onClick={() => setSelectedAuditLog(log)}
                              className="text-secondary hover:text-secondary-dark underline text-xs cursor-pointer"
                            >
                              Lihat State JSON
                            </button>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
              </div>
            </div>
          )}
        </>
      )}

      {/* Modal Detail JSON State Audit Log */}
      {selectedAuditLog && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-2xl max-w-lg w-full p-5 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b pb-3">
              <h4 className="font-bold text-sm text-gray-900">
                Detail Audit Log #{selectedAuditLog.id} — {selectedAuditLog.action}
              </h4>
              <button
                onClick={() => setSelectedAuditLog(null)}
                className="text-gray-400 hover:text-gray-600 font-bold"
              >
                ✕
              </button>
            </div>

            <div className="space-y-3 text-xs">
              <div>
                <p className="font-bold text-gray-700 mb-1">Before State:</p>
                <pre className="bg-neutral p-3 rounded-xl border border-gray-200 overflow-x-auto text-[11px]">
                  {JSON.stringify(selectedAuditLog.before_state, null, 2) || '(null)'}
                </pre>
              </div>
              <div>
                <p className="font-bold text-gray-700 mb-1">After State:</p>
                <pre className="bg-neutral p-3 rounded-xl border border-gray-200 overflow-x-auto text-[11px]">
                  {JSON.stringify(selectedAuditLog.after_state, null, 2) || '(null)'}
                </pre>
              </div>
            </div>

            <div className="text-right pt-2 border-t">
              <button
                onClick={() => setSelectedAuditLog(null)}
                className="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-xl text-xs font-semibold cursor-pointer"
              >
                Tutup
              </button>
            </div>
          </div>
        </div>
      )}
    </DashboardLayout>
  );
}
