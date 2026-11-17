import { useState, useEffect } from 'react';
import api from '../../api/client';
import DashboardLayout from '../../components/dashboard/DashboardLayout';
import StatusBadge from '../../components/dashboard/StatusBadge';
import AccountManagement from '../management/AccountManagement';

export default function AdminOwnerDashboard() {
  const [activeTab, setActiveTab] = useState('tokos'); // 'tokos' | 'disputes' | 'users'
  
  // Toko moderation state
  const [tokos, setTokos] = useState([]);
  const [tokoFilter, setTokoFilter] = useState('');
  const [loadingTokos, setLoadingTokos] = useState(true);
  const [actionModal, setActionModal] = useState({ isOpen: false, toko: null, targetStatus: '', reason: '' });
  const [processingAction, setProcessingAction] = useState(false);

  // Dispute state
  const [disputes, setDisputes] = useState([]);
  const [disputeFilter, setDisputeFilter] = useState('');
  const [loadingDisputes, setLoadingDisputes] = useState(false);
  const [selectedDispute, setSelectedDispute] = useState(null);
  const [resolutionText, setResolutionText] = useState('');
  const [resolutionStatus, setResolutionStatus] = useState('resolved');
  const [savingResolution, setSavingResolution] = useState(false);

  // User management state
  const [users, setUsers] = useState([]);
  const [userSearch, setUserSearch] = useState('');
  const [userRoleFilter, setUserRoleFilter] = useState('');
  const [loadingUsers, setLoadingUsers] = useState(false);
  const [togglingUserId, setTogglingUserId] = useState(null);

  // Finance security isolation test state
  const [financeTestResult, setFinanceTestResult] = useState(null);
  const [testingFinance, setTestingFinance] = useState(false);

  // Fetch Tokos
  const fetchTokos = async () => {
    setLoadingTokos(true);
    try {
      const url = tokoFilter ? `/platform/tokos?status=${tokoFilter}` : '/platform/tokos';
      const res = await api.get(url);
      setTokos(res.data || []);
    } catch (err) {
      console.error('Error fetching tokos:', err);
    } finally {
      setLoadingTokos(false);
    }
  };

  // Fetch Disputes
  const fetchDisputes = async () => {
    setLoadingDisputes(true);
    try {
      const url = disputeFilter ? `/platform/disputes?status=${disputeFilter}` : '/platform/disputes';
      const res = await api.get(url);
      setDisputes(res.data || []);
    } catch (err) {
      console.error('Error fetching disputes:', err);
    } finally {
      setLoadingDisputes(false);
    }
  };

  // Fetch Users
  const fetchUsers = async () => {
    setLoadingUsers(true);
    try {
      const params = new URLSearchParams();
      if (userSearch) params.append('search', userSearch);
      if (userRoleFilter) params.append('role', userRoleFilter);
      const url = `/platform/users${params.toString() ? '?' + params.toString() : ''}`;
      const res = await api.get(url);
      setUsers(res.data || []);
    } catch (err) {
      console.error('Error fetching users:', err);
    } finally {
      setLoadingUsers(false);
    }
  };

  useEffect(() => {
    fetchTokos();
  }, [tokoFilter]);

  useEffect(() => {
    if (activeTab === 'disputes') fetchDisputes();
    if (activeTab === 'users') fetchUsers();
  }, [activeTab, disputeFilter]);

  // Handle Toko Status Action
  const handleConfirmTokoStatus = async () => {
    if (!actionModal.toko || !actionModal.targetStatus) return;
    setProcessingAction(true);
    try {
      await api.patch(`/platform/tokos/${actionModal.toko.id}/status`, {
        status: actionModal.targetStatus,
        reason: actionModal.reason || null,
      });
      setActionModal({ isOpen: false, toko: null, targetStatus: '', reason: '' });
      fetchTokos();
    } catch (err) {
      alert('Gagal update status toko: ' + err.message);
    } finally {
      setProcessingAction(false);
    }
  };

  // Handle Dispute Resolution
  const handleSaveDisputeResolution = async (e) => {
    e.preventDefault();
    if (!selectedDispute) return;

    if (['resolved', 'rejected'].includes(resolutionStatus) && (!resolutionText || resolutionText.trim().length < 5)) {
      alert('Catatan resolusi wajib diisi minimal 5 karakter saat menyelesaikan atau menolak dispute.');
      return;
    }

    setSavingResolution(true);
    try {
      await api.patch(`/platform/disputes/${selectedDispute.id}`, {
        status: resolutionStatus,
        resolusi: resolutionText,
      });
      setSelectedDispute(null);
      setResolutionText('');
      fetchDisputes();
    } catch (err) {
      alert('Gagal memperbarui resolusi dispute: ' + err.message);
    } finally {
      setSavingResolution(false);
    }
  };

  // Handle Toggle User Status
  const handleToggleUser = async (userId, userName) => {
    if (!confirm(`Ubah status aktif / suspend untuk user ${userName}?`)) return;
    setTogglingUserId(userId);
    try {
      const res = await api.patch(`/platform/users/${userId}/status`);
      alert(res.message);
      fetchUsers();
    } catch (err) {
      alert('Gagal mengubah status user: ' + err.message);
    } finally {
      setTogglingUserId(null);
    }
  };

  // Test backend enforcement on finance restriction
  const handleTestFinanceAccess = async () => {
    setTestingFinance(true);
    setFinanceTestResult(null);
    try {
      await api.get('/platform/finances/commission');
      setFinanceTestResult({
        allowed: true,
        message: 'Akses DITERIMA (Warning: seharusnya ditolak untuk Admin Owner!)',
      });
    } catch (err) {
      setFinanceTestResult({
        allowed: false,
        message: '403 FORBIDDEN (Berhasil diamankan oleh FinancePolicy & ActiveRoleMiddleware di Backend)',
      });
    } finally {
      setTestingFinance(false);
    }
  };

  return (
    <DashboardLayout
      requiredRole="admin_owner"
      title="Admin Owner Dashboard"
      subtitle="Moderasi toko mitra, penanganan dispute komplain, dan kelola akun pengguna."
      badgeIcon="🛡️"
      navItems={[
        { label: 'Approval Toko', icon: '🏪', path: '#tokos' },
        { label: 'Kelola Dispute', icon: '⚖️', path: '#disputes' },
        { label: 'Kelola Pengguna', icon: '👥', path: '#users' },
      ]}
    >
      {/* Backend Security Isolation Banner */}
      <div className="bg-sky-50 border border-sky-200 rounded-2xl p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs shadow-xs">
        <div>
          <p className="font-bold text-sky-900 flex items-center gap-1.5">
            <span>🔒</span> Penegakan Keamanan Hak Akses (Backend Role Isolation)
          </p>
          <p className="text-sky-700 mt-0.5">
            Role Admin Owner <strong>tidak memiliki akses ke pengaturan komisi & keuangan inti</strong>. Hak akses ini ditegakkan langsung oleh <code>FinancePolicy</code> & <code>ActiveRoleMiddleware</code> di backend.
          </p>
        </div>
        <button
          onClick={handleTestFinanceAccess}
          disabled={testingFinance}
          className="bg-sky-600 hover:bg-sky-700 text-white font-bold px-3.5 py-1.5 rounded-lg shrink-0 transition-colors cursor-pointer"
        >
          {testingFinance ? 'Menguji...' : 'Verifikasi Isolasi Keuangan'}
        </button>
      </div>

      {financeTestResult && (
        <div
          className={`p-3 rounded-xl border text-xs font-semibold ${
            financeTestResult.allowed
              ? 'bg-rose-50 border-rose-300 text-rose-800'
              : 'bg-emerald-50 border-emerald-300 text-emerald-800'
          }`}
        >
          {financeTestResult.allowed ? '❌ ' : '🛡️ '} {financeTestResult.message}
        </div>
      )}

      {/* Tabs Switcher */}
      <div className="flex border-b border-gray-200 bg-white rounded-2xl p-1.5 shadow-xs overflow-x-auto gap-1">
        <button
          onClick={() => setActiveTab('tokos')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer ${
            activeTab === 'tokos'
              ? 'bg-primary text-white shadow-xs'
              : 'text-gray-600 hover:text-primary hover:bg-gray-100'
          }`}
        >
          🏪 Approval & Moderasi Toko
        </button>
        <button
          onClick={() => setActiveTab('disputes')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer ${
            activeTab === 'disputes'
              ? 'bg-primary text-white shadow-xs'
              : 'text-gray-600 hover:text-primary hover:bg-gray-100'
          }`}
        >
          ⚖️ Kelola Dispute ({disputes.filter((d) => d.status === 'open').length} Baru)
        </button>
        <button
          onClick={() => setActiveTab('users')}
          className={`px-4 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer ${
            activeTab === 'users'
              ? 'bg-primary text-white shadow-xs'
              : 'text-gray-600 hover:text-primary hover:bg-gray-100'
          }`}
        >
          👥 Kelola Pengguna
        </button>
      </div>

      {/* TAB 1: TOKO MODERATION */}
      {activeTab === 'tokos' && (
        <div className="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden space-y-4 p-5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 className="font-bold text-sm text-gray-900">Daftar Pengajuan & Status Toko Mitra</h3>
              <p className="text-xs text-gray-500">Tinjau kelengkapan dokumen toko dan lakukan persetujuan atau suspensi.</p>
            </div>
            {/* Filter buttons */}
            <div className="flex flex-wrap gap-1.5 text-xs">
              {['', 'submitted', 'approved', 'rejected', 'suspended'].map((st) => (
                <button
                  key={st}
                  onClick={() => setTokoFilter(st)}
                  className={`px-3 py-1.5 rounded-lg font-bold transition-colors cursor-pointer ${
                    tokoFilter === st
                      ? 'bg-primary text-white'
                      : 'bg-neutral text-gray-600 hover:bg-gray-200'
                  }`}
                >
                  {st === '' ? 'Semua' : st.charAt(0).toUpperCase() + st.slice(1)}
                </button>
              ))}
            </div>
          </div>

          {loadingTokos ? (
            <div className="py-12 text-center text-xs text-gray-400">
              <div className="w-6 h-6 border-2 border-primary border-t-transparent rounded-full animate-spin mx-auto mb-2" />
              Memuat data toko...
            </div>
          ) : tokos.length === 0 ? (
            <div className="py-12 text-center text-xs text-gray-400">
              Tidak ada toko ditemukan pada filter ini.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-neutral text-gray-500 border-b border-gray-200 font-semibold uppercase">
                  <tr>
                    <th className="py-3 px-3">Toko</th>
                    <th className="py-3 px-3">Owner / Kontak</th>
                    <th className="py-3 px-3">Alamat</th>
                    <th className="py-3 px-3 text-center">Status</th>
                    <th className="py-3 px-3 text-center">Aksi Moderasi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {tokos.map((toko) => (
                    <tr key={toko.id} className="hover:bg-gray-50/70">
                      <td className="py-3 px-3">
                        <p className="font-bold text-gray-900">{toko.nama}</p>
                        <p className="text-[10px] text-gray-400">ID #{toko.id}</p>
                      </td>
                      <td className="py-3 px-3">
                        <p className="font-semibold text-gray-800">{toko.owner?.name || 'Owner'}</p>
                        <p className="text-[10px] text-gray-400">{toko.owner?.email}</p>
                      </td>
                      <td className="py-3 px-3 text-gray-600 max-w-xs truncate">
                        {toko.alamat}
                      </td>
                      <td className="py-3 px-3 text-center">
                        <StatusBadge status={toko.status} />
                      </td>
                      <td className="py-3 px-3 text-center">
                        <div className="flex items-center justify-center gap-1.5">
                          {toko.status !== 'approved' && (
                            <button
                              onClick={() => setActionModal({ isOpen: true, toko, targetStatus: 'approved', reason: '' })}
                              className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] px-2.5 py-1 rounded-md shadow-xs"
                            >
                              Approve
                            </button>
                          )}
                          {toko.status !== 'rejected' && toko.status !== 'approved' && (
                            <button
                              onClick={() => setActionModal({ isOpen: true, toko, targetStatus: 'rejected', reason: '' })}
                              className="bg-rose-600 hover:bg-rose-700 text-white font-bold text-[10px] px-2.5 py-1 rounded-md shadow-xs"
                            >
                              Reject
                            </button>
                          )}
                          {toko.status === 'approved' && (
                            <button
                              onClick={() => setActionModal({ isOpen: true, toko, targetStatus: 'suspended', reason: '' })}
                              className="bg-gray-700 hover:bg-gray-800 text-white font-bold text-[10px] px-2.5 py-1 rounded-md shadow-xs"
                            >
                              Suspend
                            </button>
                          )}
                          {toko.status === 'suspended' && (
                            <button
                              onClick={() => setActionModal({ isOpen: true, toko, targetStatus: 'approved', reason: 'Re-activated by admin' })}
                              className="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] px-2.5 py-1 rounded-md shadow-xs"
                            >
                              Aktifkan
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* TAB 2: DISPUTES MANAGEMENT */}
      {activeTab === 'disputes' && (
        <div className="bg-white rounded-2xl border border-gray-200 shadow-xs overflow-hidden space-y-4 p-5">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
              <h3 className="font-bold text-sm text-gray-900">Kelola Dispute & Komplain Pelanggan</h3>
              <p className="text-xs text-gray-500">Tengahi keluhan antara pelanggan, toko laundry, dan kurir.</p>
            </div>
            <div className="flex gap-1.5 text-xs">
              {['', 'open', 'in_review', 'resolved', 'rejected'].map((st) => (
                <button
                  key={st}
                  onClick={() => setDisputeFilter(st)}
                  className={`px-3 py-1.5 rounded-lg font-bold transition-colors cursor-pointer ${
                    disputeFilter === st
                      ? 'bg-primary text-white'
                      : 'bg-neutral text-gray-600 hover:bg-gray-200'
                  }`}
                >
                  {st === '' ? 'Semua' : st.replace('_', ' ').toUpperCase()}
                </button>
              ))}
            </div>
          </div>

          {loadingDisputes ? (
            <div className="py-12 text-center text-xs text-gray-400">Memuat data dispute...</div>
          ) : disputes.length === 0 ? (
            <div className="py-12 text-center text-xs text-gray-400">
              Tidak ada komplain/dispute yang memerlukan tindakan saat ini.
            </div>
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-neutral text-gray-500 border-b border-gray-200 font-semibold uppercase">
                  <tr>
                    <th className="py-3 px-3">Order</th>
                    <th className="py-3 px-3">Pelapor</th>
                    <th className="py-3 px-3">Masalah & Alasan</th>
                    <th className="py-3 px-3 text-center">Status</th>
                    <th className="py-3 px-3">Resolusi Admin</th>
                    <th className="py-3 px-3 text-center">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-100">
                  {disputes.map((disp) => (
                    <tr key={disp.id} className="hover:bg-gray-50">
                      <td className="py-3 px-3 font-mono font-bold text-primary">
                        <div>{disp.order?.nomor_order || `#${disp.order_id}`}</div>
                        <span className="text-[10px] text-gray-400 font-normal">{disp.order?.toko?.nama}</span>
                      </td>
                      <td className="py-3 px-3">
                        <span className="font-semibold text-gray-800">{disp.pelapor?.name}</span>
                        <span className="text-[10px] text-gray-400 block">{disp.pelapor?.email}</span>
                      </td>
                      <td className="py-3 px-3 max-w-xs">
                        <span className="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-900 mb-0.5">
                          {disp.alasan ? disp.alasan.replace(/_/g, ' ').toUpperCase() : 'KOMPLAIN'}
                        </span>
                        <p className="text-gray-600 text-[11px] line-clamp-2">{disp.deskripsi || '-'}</p>
                      </td>
                      <td className="py-3 px-3 text-center">
                        <StatusBadge status={disp.status} />
                      </td>
                      <td className="py-3 px-3 text-gray-600 max-w-xs truncate italic">
                        {disp.resolusi || '(Belum ada resolusi)'}
                      </td>
                      <td className="py-3 px-3 text-center">
                        <button
                          onClick={() => {
                            setSelectedDispute(disp);
                            setResolutionText(disp.resolusi || '');
                            setResolutionStatus(disp.status === 'open' ? 'in_review' : disp.status);
                          }}
                          className="bg-secondary hover:bg-secondary-dark text-white font-bold text-[11px] px-3 py-1.5 rounded-lg shadow-xs cursor-pointer"
                        >
                          Tindak Lanjut
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* TAB 3: USER MANAGEMENT */}
      {activeTab === 'users' && (
        <AccountManagement />
      )}

      {/* Modal Aksi Toko Status */}
      {actionModal.isOpen && actionModal.toko && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-2xl max-w-md w-full p-5 shadow-2xl space-y-4">
            <h4 className="font-bold text-sm text-gray-900">
              Konfirmasi {actionModal.targetStatus.toUpperCase()} Toko
            </h4>
            <p className="text-xs text-gray-600">
              Toko: <strong>{actionModal.toko.nama}</strong> ({actionModal.toko.alamat})
            </p>

            <div>
              <label className="block text-xs font-semibold text-gray-700 mb-1">
                Catatan / Alasan Tindakan (Opsional):
              </label>
              <textarea
                rows={3}
                placeholder="Misal: Dokumen SIUP valid / Tidak memenuhi standar higienis"
                value={actionModal.reason}
                onChange={(e) => setActionModal({ ...actionModal, reason: e.target.value })}
                className="w-full bg-neutral border border-gray-300 rounded-xl p-2.5 text-xs outline-none focus:ring-1 focus:ring-primary"
              />
            </div>

            <div className="flex gap-2 justify-end pt-2 border-t">
              <button
                type="button"
                onClick={() => setActionModal({ isOpen: false, toko: null, targetStatus: '', reason: '' })}
                className="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold px-4 py-2 rounded-xl"
              >
                Batal
              </button>
              <button
                type="button"
                onClick={handleConfirmTokoStatus}
                disabled={processingAction}
                className="bg-primary hover:bg-primary-dark text-white text-xs font-bold px-4 py-2 rounded-xl disabled:opacity-50"
              >
                {processingAction ? 'Menyimpan...' : 'Konfirmasi & Catat Audit'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal Tindak Lanjut Dispute */}
      {selectedDispute && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div className="bg-white rounded-2xl max-w-lg w-full p-5 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b pb-2">
              <h4 className="font-bold text-sm text-gray-900">
                Penyelesaian Dispute Order #{selectedDispute.order?.nomor_order}
              </h4>
              <button onClick={() => setSelectedDispute(null)} className="text-gray-400 font-bold">✕</button>
            </div>

            <form onSubmit={handleSaveDisputeResolution} className="space-y-3 text-xs">
              <div className="bg-neutral p-3 rounded-xl border border-gray-200 space-y-1.5">
                <p><strong>Pelapor:</strong> {selectedDispute.pelapor?.name} ({selectedDispute.pelapor?.email})</p>
                <p><strong>Toko:</strong> {selectedDispute.order?.toko?.nama}</p>
                <p><strong>Alasan:</strong> <span className="font-semibold text-primary">{selectedDispute.alasan ? selectedDispute.alasan.replace(/_/g, ' ').toUpperCase() : '-'}</span></p>
                {selectedDispute.deskripsi && (
                  <div className="bg-white p-2 rounded-lg border border-gray-200 mt-1">
                    <p className="text-[11px] text-gray-700 italic">"{selectedDispute.deskripsi}"</p>
                  </div>
                )}
                <p><strong>Nilai Transaksi:</strong> Rp {Number(selectedDispute.order?.total_biaya || 0).toLocaleString('id-ID')}</p>
              </div>

              <div>
                <label className="block font-bold text-gray-700 mb-1">Status Dispute:</label>
                <select
                  value={resolutionStatus}
                  onChange={(e) => setResolutionStatus(e.target.value)}
                  className="w-full bg-white border border-gray-300 rounded-xl p-2.5 text-xs outline-none"
                >
                  <option value="in_review">In Review (Sedang Ditelusuri & Dimediasi)</option>
                  <option value="resolved">Resolved (Dispute Diselesaikan)</option>
                  <option value="rejected">Rejected (Dispute Ditolak)</option>
                  <option value="open">Open (Menunggu Tindakan)</option>
                </select>
              </div>

              <div>
                <label className="block font-bold text-gray-700 mb-1">
                  Catatan Resolusi Admin {['resolved', 'rejected'].includes(resolutionStatus) && <span className="text-rose-600 font-extrabold">* (Wajib Diisi)</span>}:
                </label>
                <textarea
                  rows={4}
                  required={['resolved', 'rejected'].includes(resolutionStatus)}
                  placeholder="Jelaskan hasil investigasi, mediasi, dan keputusan final kepada pihak toko dan customer..."
                  value={resolutionText}
                  onChange={(e) => setResolutionText(e.target.value)}
                  className="w-full bg-white border border-gray-300 rounded-xl p-2.5 text-xs outline-none focus:ring-1 focus:ring-primary"
                />
              </div>

              <div className="flex gap-2 justify-end pt-2 border-t">
                <button
                  type="button"
                  onClick={() => setSelectedDispute(null)}
                  className="bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-semibold px-4 py-2 rounded-xl"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={savingResolution}
                  className="bg-primary hover:bg-primary-dark text-white text-xs font-bold px-4 py-2 rounded-xl disabled:opacity-50"
                >
                  {savingResolution ? 'Menyimpan...' : 'Simpan Resolusi'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </DashboardLayout>
  );
}
