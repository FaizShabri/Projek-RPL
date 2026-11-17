import { useState, useEffect } from 'react';
import { useAuth } from '../../contexts/AuthContext';
import api from '../../api/client';

export default function AccountManagement() {
  const { user: currentUser, activeRole } = useAuth();
  const isPlatformOwner = activeRole === 'platform_owner';

  // Tabs: 'pending' (Platform Owner only) | 'users' | 'invitations'
  const [activeTab, setActiveTab] = useState(isPlatformOwner ? 'pending' : 'users');
  const [pendingCount, setPendingCount] = useState(0);

  // Business settings (Platform Owner)
  const [approvalMode, setApprovalMode] = useState('wajib');
  const [savingSetting, setSavingSetting] = useState(false);
  const [settingMessage, setSettingMessage] = useState(null);

  // Tab: Menunggu Persetujuan
  const [pendingAccounts, setPendingAccounts] = useState([]);
  const [pendingSearch, setPendingSearch] = useState('');
  const [pendingPage, setPendingPage] = useState(1);
  const [pendingTotalPages, setPendingTotalPages] = useState(1);
  const [pendingTotal, setPendingTotal] = useState(0);
  const [selectedPendingIds, setSelectedPendingIds] = useState([]);

  // Tab: Semua Akun
  const [users, setUsers] = useState([]);
  const [search, setSearch] = useState('');
  const [roleFilter, setRoleFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [totalUsers, setTotalUsers] = useState(0);

  // Tab: Undangan Staf
  const [invitations, setInvitations] = useState([]);
  const [myTokos, setMyTokos] = useState([]);

  // General Loading & Feedback State
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [successMessage, setSuccessMessage] = useState(null);
  const [actionLoading, setActionLoading] = useState(false);

  // Modals state
  const [detailModal, setDetailModal] = useState({ isOpen: false, user: null, summary: null, loading: false });
  const [statusModal, setStatusModal] = useState({ isOpen: false, user: null, targetStatus: '' });
  const [roleModal, setRoleModal] = useState({ isOpen: false, user: null, targetRole: '' });
  const [revokeModal, setRevokeModal] = useState({ isOpen: false, user: null });
  const [resetModal, setResetModal] = useState({ isOpen: false, user: null });
  const [inviteModal, setInviteModal] = useState({ isOpen: false, email: '', role: 'kurir', tokoId: '' });

  // Approval / Rejection Modals
  const [approveModal, setApproveModal] = useState({ isOpen: false, user: null });
  const [rejectModal, setRejectModal] = useState({ isOpen: false, user: null, reason: '' });
  const [bulkApproveModal, setBulkApproveModal] = useState({ isOpen: false });
  const [bulkRejectModal, setBulkRejectModal] = useState({ isOpen: false, reason: '' });

  // Soft Delete Modal (2-Step Confirm)
  const [deleteModal, setDeleteModal] = useState({ isOpen: false, user: null, confirmedStep1: false });

  // ---------------- FETCHERS ----------------

  const fetchPendingCount = async () => {
    if (!isPlatformOwner) return;
    try {
      const res = await api.get('/management/pending-accounts/count');
      setPendingCount(res.count || 0);
    } catch (err) {
      console.error('Error fetching pending count:', err);
    }
  };

  const fetchSettings = async () => {
    if (!isPlatformOwner) return;
    try {
      const res = await api.get('/management/settings');
      if (res.account_approval_mode) {
        setApprovalMode(res.account_approval_mode);
      }
    } catch (err) {
      console.error('Error fetching settings:', err);
    }
  };

  const fetchPendingAccounts = async () => {
    try {
      setLoading(true);
      setError(null);
      const params = new URLSearchParams();
      if (pendingSearch.trim()) params.append('search', pendingSearch.trim());
      params.append('page', pendingPage);

      const res = await api.get(`/management/pending-accounts?${params.toString()}`);
      setPendingAccounts(res.data || []);
      setPendingTotalPages(res.last_page || 1);
      setPendingTotal(res.total || 0);
      setSelectedPendingIds([]);
    } catch (err) {
      setError(err.message || 'Gagal memuat daftar akun menunggu persetujuan.');
    } finally {
      setLoading(false);
    }
  };

  const fetchUsers = async () => {
    try {
      setLoading(true);
      setError(null);
      const params = new URLSearchParams();
      if (search.trim()) params.append('search', search.trim());
      if (roleFilter) params.append('role', roleFilter);
      if (statusFilter) params.append('status', statusFilter);
      params.append('page', page);

      const res = await api.get(`/management/users?${params.toString()}`);
      setUsers(res.data || []);
      setTotalPages(res.last_page || 1);
      setTotalUsers(res.total || 0);
    } catch (err) {
      setError(err.message || 'Gagal memuat daftar pengguna.');
    } finally {
      setLoading(false);
    }
  };

  const fetchInvitations = async () => {
    try {
      setLoading(true);
      setError(null);
      const res = await api.get('/management/invitations');
      setInvitations(res.data || []);
    } catch (err) {
      setError(err.message || 'Gagal memuat daftar undangan staf.');
    } finally {
      setLoading(false);
    }
  };

  const fetchMyTokos = async () => {
    try {
      if (activeRole === 'owner_toko') {
        const res = await api.get('/owner/tokos');
        setMyTokos(res.tokos || []);
        if (res.tokos?.length > 0) {
          setInviteModal((prev) => ({ ...prev, tokoId: res.tokos[0].id }));
        }
      }
    } catch (err) {
      console.error('Error fetching tokos for invite:', err);
    }
  };

  // Initial & Tab Change Effects
  useEffect(() => {
    if (isPlatformOwner) {
      fetchPendingCount();
      fetchSettings();
    }
    fetchMyTokos();
  }, [activeRole, isPlatformOwner]);

  useEffect(() => {
    if (activeTab === 'pending' && isPlatformOwner) {
      fetchPendingAccounts();
    } else if (activeTab === 'users') {
      fetchUsers();
    } else if (activeTab === 'invitations') {
      fetchInvitations();
    }
  }, [activeTab, page, pendingPage, roleFilter, statusFilter]);

  // ---------------- ACTIONS: APPROVAL & REJECTION ----------------

  const handleApprove = async () => {
    if (!approveModal.user) return;
    try {
      setActionLoading(true);
      const res = await api.post(`/management/users/${approveModal.user.id}/approve`);
      setSuccessMessage(res.message);
      setApproveModal({ isOpen: false, user: null });
      fetchPendingCount();
      fetchPendingAccounts();
    } catch (err) {
      alert('Gagal menyetujui akun: ' + err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handleReject = async (e) => {
    e.preventDefault();
    if (!rejectModal.user) return;
    if (!rejectModal.reason || rejectModal.reason.trim().length < 5) {
      alert('Alasan penolakan wajib diisi (minimal 5 karakter).');
      return;
    }
    try {
      setActionLoading(true);
      const res = await api.post(`/management/users/${rejectModal.user.id}/reject`, {
        reason: rejectModal.reason.trim(),
      });
      setSuccessMessage(res.message);
      setRejectModal({ isOpen: false, user: null, reason: '' });
      fetchPendingCount();
      fetchPendingAccounts();
    } catch (err) {
      alert('Gagal menolak akun: ' + err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handleBulkApprove = async () => {
    if (selectedPendingIds.length === 0) return;
    try {
      setActionLoading(true);
      const res = await api.post('/management/users/bulk-approve', {
        ids: selectedPendingIds,
      });
      setSuccessMessage(res.message);
      setBulkApproveModal({ isOpen: false });
      setSelectedPendingIds([]);
      fetchPendingCount();
      fetchPendingAccounts();
    } catch (err) {
      alert('Gagal menyetujui massal: ' + err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handleBulkReject = async (e) => {
    e.preventDefault();
    if (selectedPendingIds.length === 0) return;
    if (!bulkRejectModal.reason || bulkRejectModal.reason.trim().length < 5) {
      alert('Alasan penolakan massal wajib diisi (minimal 5 karakter).');
      return;
    }
    try {
      setActionLoading(true);
      const res = await api.post('/management/users/bulk-reject', {
        ids: selectedPendingIds,
        reason: bulkRejectModal.reason.trim(),
      });
      setSuccessMessage(res.message);
      setBulkRejectModal({ isOpen: false, reason: '' });
      setSelectedPendingIds([]);
      fetchPendingCount();
      fetchPendingAccounts();
    } catch (err) {
      alert('Gagal menolak massal: ' + err.message);
    } finally {
      setActionLoading(false);
    }
  };

  // ---------------- ACTIONS: SETTINGS ----------------

  const handleSaveSetting = async () => {
    try {
      setSavingSetting(true);
      setSettingMessage(null);
      const res = await api.put('/management/settings', {
        account_approval_mode: approvalMode,
      });
      setSettingMessage(res.message || 'Pengaturan berhasil disimpan.');
    } catch (err) {
      alert('Gagal menyimpan pengaturan: ' + err.message);
    } finally {
      setSavingSetting(false);
    }
  };

  // ---------------- ACTIONS: USER MANAGEMENT ----------------

  const openDetailModal = async (targetUser) => {
    setDetailModal({ isOpen: true, user: targetUser, summary: null, loading: true });
    try {
      const res = await api.get(`/management/users/${targetUser.id}`);
      setDetailModal({ isOpen: true, user: res.user, summary: res.summary, loading: false });
    } catch (err) {
      alert('Gagal memuat detail user: ' + err.message);
      setDetailModal({ isOpen: false, user: null, summary: null, loading: false });
    }
  };

  const handleUpdateStatus = async () => {
    if (!statusModal.user) return;
    try {
      setActionLoading(true);
      const res = await api.patch(`/management/users/${statusModal.user.id}/status`, {
        status: statusModal.targetStatus,
      });
      setSuccessMessage(res.message);
      setStatusModal({ isOpen: false, user: null, targetStatus: '' });
      fetchUsers();
    } catch (err) {
      alert('Gagal mengubah status: ' + err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handleUpdateRole = async () => {
    if (!roleModal.user) return;
    try {
      setActionLoading(true);
      const res = await api.patch(`/management/users/${roleModal.user.id}/role`, {
        role: roleModal.targetRole,
      });
      setSuccessMessage(res.message);
      setRoleModal({ isOpen: false, user: null, targetRole: '' });
      fetchUsers();
    } catch (err) {
      alert('Gagal mengubah role: ' + err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handleRevokeSessions = async () => {
    if (!revokeModal.user) return;
    try {
      setActionLoading(true);
      const res = await api.post(`/management/users/${revokeModal.user.id}/revoke-sessions`);
      setSuccessMessage(res.message);
      setRevokeModal({ isOpen: false, user: null });
      fetchUsers();
    } catch (err) {
      alert('Gagal mencabut sesi: ' + err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handleTriggerResetPassword = async () => {
    if (!resetModal.user) return;
    try {
      setActionLoading(true);
      const res = await api.post(`/management/users/${resetModal.user.id}/reset-password`);
      setSuccessMessage(res.message);
      setResetModal({ isOpen: false, user: null });
    } catch (err) {
      alert('Gagal mengirim tautan reset password: ' + err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handleDeleteUser = async () => {
    if (!deleteModal.user) return;
    if (!deleteModal.confirmedStep1) {
      alert('Silakan centang persetujuan konfirmasi terlebih dahulu.');
      return;
    }
    try {
      setActionLoading(true);
      const res = await api.delete(`/management/users/${deleteModal.user.id}`);
      setSuccessMessage(res.message);
      setDeleteModal({ isOpen: false, user: null, confirmedStep1: false });
      fetchUsers();
    } catch (err) {
      alert('Gagal menghapus akun: ' + err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handleSendInvite = async (e) => {
    e.preventDefault();
    try {
      setActionLoading(true);
      const payload = {
        email: inviteModal.email.trim(),
        role: inviteModal.role,
      };
      if (inviteModal.tokoId) {
        payload.toko_id = inviteModal.tokoId;
      }
      const res = await api.post('/invitations', payload);
      setSuccessMessage(res.message);
      setInviteModal({ isOpen: false, email: '', role: 'kurir', tokoId: '' });
      if (activeTab === 'invitations') {
        fetchInvitations();
      }
    } catch (err) {
      alert('Gagal mengirim undangan staf: ' + err.message);
    } finally {
      setActionLoading(false);
    }
  };

  const handleCancelInvite = async (invitationId) => {
    if (!confirm('Apakah Anda yakin ingin membatalkan undangan staf ini?')) return;
    try {
      const res = await api.post(`/invitations/${invitationId}/cancel`);
      setSuccessMessage(res.message);
      fetchInvitations();
    } catch (err) {
      alert('Gagal membatalkan undangan: ' + err.message);
    }
  };

  // Helper Badges
  const getRoleBadge = (roleName) => {
    switch (roleName) {
      case 'platform_owner':
        return <span className="bg-purple-100 text-purple-800 font-bold px-2.5 py-0.5 rounded-full text-[11px] border border-purple-200">👑 Platform Owner</span>;
      case 'admin_owner':
        return <span className="bg-indigo-100 text-indigo-800 font-bold px-2.5 py-0.5 rounded-full text-[11px] border border-indigo-200">🛡️ Admin Owner</span>;
      case 'owner_toko':
        return <span className="bg-blue-100 text-blue-800 font-bold px-2.5 py-0.5 rounded-full text-[11px] border border-blue-200">🏪 Owner Toko</span>;
      case 'admin_toko':
        return <span className="bg-sky-100 text-sky-800 font-bold px-2.5 py-0.5 rounded-full text-[11px] border border-sky-200">👔 Admin Toko</span>;
      case 'kurir':
        return <span className="bg-amber-100 text-amber-800 font-bold px-2.5 py-0.5 rounded-full text-[11px] border border-amber-200">🏍️ Kurir</span>;
      default:
        return <span className="bg-emerald-100 text-emerald-800 font-bold px-2.5 py-0.5 rounded-full text-[11px] border border-emerald-200">🧺 Customer</span>;
    }
  };

  const getStatusBadge = (status, rejectionReason = null) => {
    switch (status) {
      case 'active':
        return (
          <span className="inline-flex items-center gap-1.5 bg-emerald-50 text-emerald-700 border border-emerald-200 px-2.5 py-0.5 rounded-full text-[11px] font-semibold">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Aktif
          </span>
        );
      case 'pending_approval':
        return (
          <span className="inline-flex items-center gap-1.5 bg-amber-50 text-amber-800 border border-amber-200 px-2.5 py-0.5 rounded-full text-[11px] font-semibold">
            <span className="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span> Menunggu Persetujuan
          </span>
        );
      case 'pending_email':
        return (
          <span className="inline-flex items-center gap-1.5 bg-sky-50 text-sky-800 border border-sky-200 px-2.5 py-0.5 rounded-full text-[11px] font-semibold">
            <span className="w-1.5 h-1.5 rounded-full bg-sky-500"></span> Menunggu Verifikasi Email
          </span>
        );
      case 'rejected':
        return (
          <span
            className="inline-flex items-center gap-1.5 bg-rose-50 text-rose-800 border border-rose-200 px-2.5 py-0.5 rounded-full text-[11px] font-semibold"
            title={rejectionReason ? `Alasan: ${rejectionReason}` : 'Pendaftaran ditolak'}
          >
            <span className="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Ditolak
          </span>
        );
      case 'disabled':
      default:
        return (
          <span className="inline-flex items-center gap-1.5 bg-red-50 text-red-700 border border-red-200 px-2.5 py-0.5 rounded-full text-[11px] font-semibold">
            <span className="w-1.5 h-1.5 rounded-full bg-red-500"></span> Dinonaktifkan
          </span>
        );
    }
  };

  // Selection toggle for pending items
  const toggleSelectAllPending = () => {
    if (selectedPendingIds.length === pendingAccounts.length) {
      setSelectedPendingIds([]);
    } else {
      setSelectedPendingIds(pendingAccounts.map((a) => a.id));
    }
  };

  const toggleSelectPendingId = (id) => {
    setSelectedPendingIds((prev) =>
      prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
    );
  };

  return (
    <div className="space-y-6">
      {/* Top Banner / Actions */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
        <div>
          <h2 className="text-xl font-black text-gray-900 tracking-tight flex items-center gap-2">
            <span>👥</span> Manajemen Akun & Hak Akses
          </h2>
          <p className="text-xs text-gray-500 mt-1">
            Kelola verifikasi, persetujuan pendaftaran akun, status login, dan sistem undangan staf dengan prinsip Least Privilege.
          </p>
        </div>

        <div className="flex items-center gap-2">
          <button
            onClick={() =>
              setInviteModal({
                isOpen: true,
                email: '',
                role: activeRole === 'owner_toko' ? 'admin_toko' : 'kurir',
                tokoId: myTokos[0]?.id || '',
              })
            }
            className="bg-[#1A3A5C] text-white px-4 py-2.5 rounded-xl font-bold text-xs hover:bg-[#152e4a] transition shadow-xs cursor-pointer flex items-center gap-1.5"
          >
            <span>✉️</span> Undang Staf Baru
          </button>
        </div>
      </div>

      {/* Global Alert Messages */}
      {successMessage && (
        <div className="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center justify-between">
          <span>✅ {successMessage}</span>
          <button onClick={() => setSuccessMessage(null)} className="text-emerald-900 font-bold">✕</button>
        </div>
      )}

      {error && (
        <div className="p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-center justify-between">
          <span>⚠️ {error}</span>
          <button onClick={() => setError(null)} className="text-red-900 font-bold">✕</button>
        </div>
      )}

      {/* Settings Card (Platform Owner Only) */}
      {isPlatformOwner && (
        <div className="bg-gradient-to-r from-slate-50 to-blue-50/40 p-5 rounded-3xl border border-blue-100 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="space-y-1">
            <h3 className="text-xs font-black uppercase tracking-wider text-[#1A3A5C] flex items-center gap-1.5">
              <span>⚙️</span> Kebijakan Persetujuan Registrasi Akun Baru
            </h3>
            <p className="text-xs text-gray-600">
              Tentukan apakah pendaftar baru harus ditinjau dan disetujui secara manual oleh Platform Owner atau langsung otomatis aktif setelah verifikasi email.
            </p>
            {settingMessage && (
              <p className="text-[11px] text-emerald-700 font-bold mt-1">✓ {settingMessage}</p>
            )}
          </div>

          <div className="flex items-center gap-3">
            <div className="flex bg-white rounded-xl border border-gray-300 p-1 text-xs font-semibold">
              <button
                type="button"
                onClick={() => setApprovalMode('wajib')}
                className={`px-3 py-1.5 rounded-lg transition ${
                  approvalMode === 'wajib'
                    ? 'bg-[#1A3A5C] text-white shadow-xs font-bold'
                    : 'text-gray-600 hover:bg-gray-100'
                }`}
              >
                🔒 Wajib Disetujui (Default)
              </button>
              <button
                type="button"
                onClick={() => setApprovalMode('otomatis')}
                className={`px-3 py-1.5 rounded-lg transition ${
                  approvalMode === 'otomatis'
                    ? 'bg-[#1A3A5C] text-white shadow-xs font-bold'
                    : 'text-gray-600 hover:bg-gray-100'
                }`}
              >
                ⚡ Otomatis Aktif
              </button>
            </div>

            <button
              onClick={handleSaveSetting}
              disabled={savingSetting}
              className="bg-primary hover:bg-[#152e4a] text-white px-3.5 py-2 rounded-xl text-xs font-bold transition shadow-xs cursor-pointer disabled:opacity-50"
            >
              {savingSetting ? 'Menyimpan...' : 'Simpan'}
            </button>
          </div>
        </div>
      )}

      {/* Tab Switcher */}
      <div className="flex border-b border-gray-200 bg-white rounded-2xl p-1.5 shadow-xs gap-1 max-w-xl">
        {isPlatformOwner && (
          <button
            onClick={() => { setActiveTab('pending'); setPendingPage(1); }}
            className={`flex-1 py-2 px-3 rounded-xl text-xs font-bold transition text-center cursor-pointer flex items-center justify-center gap-1.5 ${
              activeTab === 'pending' ? 'bg-[#1A3A5C] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100'
            }`}
          >
            <span>⏳ Menunggu Persetujuan</span>
            {pendingCount > 0 && (
              <span className={`px-2 py-0.5 rounded-full text-[10px] font-black ${
                activeTab === 'pending' ? 'bg-amber-400 text-slate-900' : 'bg-amber-100 text-amber-800'
              }`}>
                {pendingCount}
              </span>
            )}
          </button>
        )}

        <button
          onClick={() => { setActiveTab('users'); setPage(1); }}
          className={`flex-1 py-2 px-3 rounded-xl text-xs font-bold transition text-center cursor-pointer ${
            activeTab === 'users' ? 'bg-[#1A3A5C] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100'
          }`}
        >
          Semua Akun ({totalUsers})
        </button>

        <button
          onClick={() => { setActiveTab('invitations'); setPage(1); }}
          className={`flex-1 py-2 px-3 rounded-xl text-xs font-bold transition text-center cursor-pointer ${
            activeTab === 'invitations' ? 'bg-[#1A3A5C] text-white shadow-xs' : 'text-gray-600 hover:bg-gray-100'
          }`}
        >
          Undangan Staf
        </button>
      </div>

      {/* ================= TAB 1: MENUNGGU PERSETUJUAN ================= */}
      {activeTab === 'pending' && isPlatformOwner && (
        <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden space-y-0">
          {/* Action Bar & Search */}
          <div className="p-4 sm:p-5 border-b border-gray-100 bg-gray-50/50 flex flex-col md:flex-row gap-3 items-center justify-between">
            <form
              onSubmit={(e) => { e.preventDefault(); setPendingPage(1); fetchPendingAccounts(); }}
              className="flex items-center gap-2 w-full md:w-auto flex-1 max-w-md"
            >
              <input
                type="text"
                placeholder="Cari nama atau email menunggu..."
                value={pendingSearch}
                onChange={(e) => setPendingSearch(e.target.value)}
                className="w-full px-3.5 py-2 bg-white border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-[#1A3A5C] focus:outline-none"
              />
              <button
                type="submit"
                className="bg-gray-800 text-white px-3.5 py-2 rounded-xl text-xs font-bold hover:bg-black transition cursor-pointer"
              >
                Cari
              </button>
            </form>

            {/* Bulk Action Controls */}
            {selectedPendingIds.length > 0 && (
              <div className="flex items-center gap-2 bg-blue-50 px-3 py-1.5 rounded-xl border border-blue-200">
                <span className="text-xs font-bold text-blue-900">
                  {selectedPendingIds.length} dipilih
                </span>
                <button
                  onClick={() => setBulkApproveModal({ isOpen: true })}
                  className="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer"
                >
                  Setujui Terpilih ({selectedPendingIds.length})
                </button>
                <button
                  onClick={() => setBulkRejectModal({ isOpen: true, reason: '' })}
                  className="bg-rose-600 hover:bg-rose-700 text-white px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer"
                >
                  Tolak Terpilih ({selectedPendingIds.length})
                </button>
                <button
                  onClick={() => setSelectedPendingIds([])}
                  className="text-gray-500 hover:text-gray-700 text-xs font-semibold px-2 cursor-pointer"
                >
                  Batal
                </button>
              </div>
            )}
          </div>

          {/* Pending Table */}
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-gray-50/80 text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                  <th className="py-3.5 px-4 sm:px-6 w-10 text-center">
                    <input
                      type="checkbox"
                      checked={pendingAccounts.length > 0 && selectedPendingIds.length === pendingAccounts.length}
                      onChange={toggleSelectAllPending}
                      className="rounded border-gray-300 text-[#1A3A5C] focus:ring-[#1A3A5C]"
                    />
                  </th>
                  <th className="py-3.5 px-4">Calon Pengguna</th>
                  <th className="py-3.5 px-4">Verifikasi Email</th>
                  <th className="py-3.5 px-4">Tanggal Mendaftar</th>
                  <th className="py-3.5 px-4 sm:px-6 text-right">Aksi Eksplisit</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 text-xs">
                {loading ? (
                  <tr>
                    <td colSpan="5" className="py-12 text-center text-gray-400">
                      <div className="w-6 h-6 border-2 border-[#1A3A5C] border-t-transparent rounded-full animate-spin mx-auto mb-2" />
                      Memuat antrean persetujuan akun...
                    </td>
                  </tr>
                ) : pendingAccounts.length === 0 ? (
                  <tr>
                    <td colSpan="5" className="py-12 text-center text-gray-400">
                      🎉 Tidak ada akun yang sedang menunggu persetujuan.
                    </td>
                  </tr>
                ) : (
                  pendingAccounts.map((item) => {
                    const isSelected = selectedPendingIds.includes(item.id);
                    return (
                      <tr
                        key={item.id}
                        className={`hover:bg-gray-50/80 transition-colors ${isSelected ? 'bg-blue-50/30' : ''}`}
                      >
                        <td className="py-3.5 px-4 sm:px-6 text-center">
                          <input
                            type="checkbox"
                            checked={isSelected}
                            onChange={() => toggleSelectPendingId(item.id)}
                            className="rounded border-gray-300 text-[#1A3A5C] focus:ring-[#1A3A5C]"
                          />
                        </td>
                        <td className="py-3.5 px-4">
                          <div className="flex items-center gap-3">
                            <div className="w-9 h-9 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-xs uppercase shadow-2xs">
                              {item.name?.substring(0, 2) || 'PL'}
                            </div>
                            <div>
                              <div className="font-bold text-gray-900">{item.name}</div>
                              <div className="text-[11px] text-gray-500">{item.email}</div>
                            </div>
                          </div>
                        </td>
                        <td className="py-3.5 px-4 whitespace-nowrap">
                          {item.email_verified_at ? (
                            <span className="inline-flex items-center gap-1 text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full text-[11px] font-semibold">
                              ✓ Terverifikasi ({new Date(item.email_verified_at).toLocaleDateString('id-ID')})
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 text-amber-700 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full text-[11px] font-semibold">
                              ⚠️ Belum Terverifikasi
                            </span>
                          )}
                        </td>
                        <td className="py-3.5 px-4 text-gray-500 text-[11px] whitespace-nowrap">
                          {new Date(item.created_at).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })}
                        </td>
                        <td className="py-3.5 px-4 sm:px-6 text-right whitespace-nowrap">
                          <div className="flex items-center justify-end gap-2">
                            <button
                              onClick={() => setApproveModal({ isOpen: true, user: item })}
                              className="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold text-xs shadow-xs cursor-pointer transition flex items-center gap-1"
                            >
                              <span>✓</span> Setujui
                            </button>
                            <button
                              onClick={() => setRejectModal({ isOpen: true, user: item, reason: '' })}
                              className="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-xl font-bold text-xs cursor-pointer transition flex items-center gap-1"
                            >
                              <span>✕</span> Tolak
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>

          {/* Pending Pagination */}
          {pendingTotalPages > 1 && (
            <div className="p-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
              <span>Halaman {pendingPage} dari {pendingTotalPages} (Total {pendingTotal} calon)</span>
              <div className="flex items-center gap-2">
                <button
                  disabled={pendingPage <= 1}
                  onClick={() => setPendingPage(pendingPage - 1)}
                  className="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40 cursor-pointer"
                >
                  Sebelumnya
                </button>
                <button
                  disabled={pendingPage >= pendingTotalPages}
                  onClick={() => setPendingPage(pendingPage + 1)}
                  className="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40 cursor-pointer"
                >
                  Selanjutnya
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* ================= TAB 2: SEMUA AKUN ================= */}
      {activeTab === 'users' && (
        <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
          {/* Filter Bar */}
          <div className="p-4 sm:p-5 border-b border-gray-100 bg-gray-50/50 flex flex-col md:flex-row gap-3 items-center justify-between">
            <form
              onSubmit={(e) => { e.preventDefault(); setPage(1); fetchUsers(); }}
              className="flex items-center gap-2 w-full md:w-auto flex-1 max-w-md"
            >
              <input
                type="text"
                placeholder="Cari nama atau email..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="w-full px-3.5 py-2 bg-white border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-[#1A3A5C] focus:outline-none"
              />
              <button
                type="submit"
                className="bg-gray-800 text-white px-3.5 py-2 rounded-xl text-xs font-bold hover:bg-black transition cursor-pointer"
              >
                Cari
              </button>
            </form>

            <div className="flex items-center gap-2.5 w-full md:w-auto">
              <select
                value={roleFilter}
                onChange={(e) => { setRoleFilter(e.target.value); setPage(1); }}
                className="px-3 py-2 bg-white border border-gray-300 rounded-xl text-xs font-medium focus:ring-2 focus:ring-[#1A3A5C] focus:outline-none"
              >
                <option value="">Semua Role</option>
                {activeRole === 'platform_owner' && (
                  <>
                    <option value="platform_owner">Platform Owner</option>
                    <option value="admin_owner">Admin Owner</option>
                  </>
                )}
                <option value="owner_toko">Owner Toko</option>
                <option value="admin_toko">Admin Toko</option>
                <option value="kurir">Kurir</option>
                {activeRole !== 'owner_toko' && <option value="customer">Customer</option>}
              </select>

              <select
                value={statusFilter}
                onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
                className="px-3 py-2 bg-white border border-gray-300 rounded-xl text-xs font-medium focus:ring-2 focus:ring-[#1A3A5C] focus:outline-none"
              >
                <option value="">Semua Status</option>
                <option value="active">Aktif</option>
                <option value="pending_approval">Menunggu Persetujuan</option>
                <option value="pending_email">Menunggu Verifikasi Email</option>
                <option value="rejected">Ditolak</option>
                <option value="disabled">Dinonaktifkan</option>
              </select>
            </div>
          </div>

          {/* Table */}
          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse">
              <thead>
                <tr className="bg-gray-50/80 text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b border-gray-100">
                  <th className="py-3.5 px-4 sm:px-6">Pengguna</th>
                  <th className="py-3.5 px-4">Role</th>
                  <th className="py-3.5 px-4">Status Akun</th>
                  <th className="py-3.5 px-4">Login Terakhir</th>
                  <th className="py-3.5 px-4 sm:px-6 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100 text-xs">
                {loading ? (
                  <tr>
                    <td colSpan="5" className="py-12 text-center text-gray-400">
                      <div className="w-6 h-6 border-2 border-[#1A3A5C] border-t-transparent rounded-full animate-spin mx-auto mb-2" />
                      Memuat data pengguna...
                    </td>
                  </tr>
                ) : users.length === 0 ? (
                  <tr>
                    <td colSpan="5" className="py-12 text-center text-gray-400">
                      Tidak ada pengguna yang sesuai dengan filter.
                    </td>
                  </tr>
                ) : (
                  users.map((item) => {
                    const primaryRole = item.roles?.[0]?.name || 'customer';
                    const isSelf = currentUser?.id === item.id;
                    const isPlatformOwnerRole = primaryRole === 'platform_owner';

                    return (
                      <tr key={item.id} className="hover:bg-gray-50/80 transition-colors">
                        <td className="py-3.5 px-4 sm:px-6">
                          <div className="flex items-center gap-3">
                            <div className="w-9 h-9 rounded-full bg-slate-200 text-slate-700 flex items-center justify-center font-bold text-xs uppercase shadow-2xs">
                              {item.name?.substring(0, 2) || 'PL'}
                            </div>
                            <div>
                              <div className="font-bold text-gray-900 flex items-center gap-1.5">
                                <span>{item.name}</span>
                                {isSelf && (
                                  <span className="text-[10px] bg-blue-100 text-blue-800 px-1.5 py-0.2 rounded font-semibold">Anda</span>
                                )}
                              </div>
                              <div className="text-[11px] text-gray-500 flex items-center gap-1 mt-0.5">
                                <span>{item.email}</span>
                                {item.email_verified_at ? (
                                  <span className="text-emerald-600 font-bold" title="Email terverifikasi">✓</span>
                                ) : (
                                  <span className="text-amber-500 font-bold" title="Email belum diverifikasi">⚠️ Belum Verif</span>
                                )}
                              </div>
                            </div>
                          </div>
                        </td>

                        <td className="py-3.5 px-4 whitespace-nowrap">
                          {getRoleBadge(primaryRole)}
                        </td>

                        <td className="py-3.5 px-4 whitespace-nowrap">
                          {getStatusBadge(item.status, item.rejection_reason)}
                        </td>

                        <td className="py-3.5 px-4 text-gray-500 text-[11px] whitespace-nowrap">
                          {item.last_login_at
                            ? new Date(item.last_login_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' })
                            : 'Belum pernah'}
                        </td>

                        <td className="py-3.5 px-4 sm:px-6 text-right whitespace-nowrap">
                          <div className="flex items-center justify-end gap-1.5">
                            <button
                              onClick={() => openDetailModal(item)}
                              className="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-semibold text-[11px] cursor-pointer"
                              title="Lihat Detail & Ringkasan"
                            >
                              Detail
                            </button>

                            {/* Ubah Role */}
                            {!isSelf && (
                              <button
                                onClick={() => setRoleModal({ isOpen: true, user: item, targetRole: primaryRole })}
                                className="px-2.5 py-1 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 rounded-lg font-semibold text-[11px] cursor-pointer"
                              >
                                Role
                              </button>
                            )}

                            {/* Toggle Status */}
                            {!isSelf && (
                              <button
                                onClick={() => setStatusModal({
                                  isOpen: true,
                                  user: item,
                                  targetStatus: item.status === 'active' ? 'disabled' : 'active',
                                })}
                                className={`px-2.5 py-1 rounded-lg font-semibold text-[11px] cursor-pointer border ${
                                  item.status === 'active'
                                    ? 'bg-amber-50 hover:bg-amber-100 text-amber-800 border-amber-200'
                                    : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-800 border-emerald-200'
                                }`}
                              >
                                {item.status === 'active' ? 'Nonaktifkan' : 'Aktifkan'}
                              </button>
                            )}

                            {/* Cabut Sesi */}
                            <button
                              onClick={() => setRevokeModal({ isOpen: true, user: item })}
                              className="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-semibold text-[11px] cursor-pointer"
                              title="Cabut Seluruh Sesi Login Aktif"
                            >
                              Cabut Sesi
                            </button>

                            {/* Reset Password Link */}
                            <button
                              onClick={() => setResetModal({ isOpen: true, user: item })}
                              className="px-2.5 py-1 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 rounded-lg font-semibold text-[11px] cursor-pointer"
                              title="Kirim Tautan Reset Password"
                            >
                              Reset PW
                            </button>

                            {/* Soft Delete (Platform Owner Only, not self, not platform_owner) */}
                            {isPlatformOwner && !isSelf && !isPlatformOwnerRole && (
                              <button
                                onClick={() => setDeleteModal({ isOpen: true, user: item, confirmedStep1: false })}
                                className="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-lg font-semibold text-[11px] cursor-pointer"
                                title="Hapus Akun Pengguna (Soft Delete)"
                              >
                                Hapus
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>

          {/* Pagination */}
          {totalPages > 1 && (
            <div className="p-4 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
              <span>Halaman {page} dari {totalPages} (Total {totalUsers} akun)</span>
              <div className="flex items-center gap-2">
                <button
                  disabled={page <= 1}
                  onClick={() => setPage(page - 1)}
                  className="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40 cursor-pointer"
                >
                  Sebelumnya
                </button>
                <button
                  disabled={page >= totalPages}
                  onClick={() => setPage(page + 1)}
                  className="px-3 py-1.5 border border-gray-300 rounded-lg hover:bg-gray-50 disabled:opacity-40 cursor-pointer"
                >
                  Selanjutnya
                </button>
              </div>
            </div>
          )}
        </div>
      )}

      {/* ================= TAB 3: UNDANGAN STAF ================= */}
      {activeTab === 'invitations' && (
        <div className="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
          <div className="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
            <h3 className="text-xs font-bold text-gray-700 uppercase tracking-wider">
              Daftar Tautan Undangan Staf Terkirim
            </h3>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left border-collapse text-xs">
              <thead>
                <tr className="bg-gray-50 text-[11px] font-bold text-gray-500 uppercase border-b border-gray-100">
                  <th className="py-3 px-4 sm:px-6">Email Calon Staf</th>
                  <th className="py-3 px-4">Role Ditugaskan</th>
                  <th className="py-3 px-4">Toko Penugasan</th>
                  <th className="py-3 px-4">Status Undangan</th>
                  <th className="py-3 px-4">Kedaluwarsa</th>
                  <th className="py-3 px-4 sm:px-6 text-right">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {loading ? (
                  <tr>
                    <td colSpan="6" className="py-8 text-center text-gray-400">
                      Memuat daftar undangan...
                    </td>
                  </tr>
                ) : invitations.length === 0 ? (
                  <tr>
                    <td colSpan="6" className="py-8 text-center text-gray-400">
                      Belum ada undangan staf yang dikirimkan.
                    </td>
                  </tr>
                ) : (
                  invitations.map((inv) => {
                    const isPending = !inv.used_at && !inv.cancelled_at && new Date(inv.expires_at) > new Date();

                    return (
                      <tr key={inv.id} className="hover:bg-gray-50">
                        <td className="py-3 px-4 sm:px-6 font-bold text-gray-900">
                          {inv.email}
                        </td>
                        <td className="py-3 px-4">
                          {getRoleBadge(inv.role)}
                        </td>
                        <td className="py-3 px-4 text-gray-600">
                          {inv.toko?.nama || '-'}
                        </td>
                        <td className="py-3 px-4">
                          {inv.used_at ? (
                            <span className="text-emerald-700 bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full text-[10px] font-bold">
                              ✓ Diterima
                            </span>
                          ) : inv.cancelled_at ? (
                            <span className="text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full text-[10px] font-bold">
                              ✕ Dibatalkan
                            </span>
                          ) : isPending ? (
                            <span className="text-amber-800 bg-amber-50 border border-amber-200 px-2 py-0.5 rounded-full text-[10px] font-bold animate-pulse">
                              ⏳ Menunggu Respon
                            </span>
                          ) : (
                            <span className="text-red-700 bg-red-50 border border-red-200 px-2 py-0.5 rounded-full text-[10px] font-bold">
                              Kedaluwarsa
                            </span>
                          )}
                        </td>
                        <td className="py-3 px-4 text-gray-500 text-[11px]">
                          {new Date(inv.expires_at).toLocaleDateString('id-ID')}
                        </td>
                        <td className="py-3 px-4 sm:px-6 text-right">
                          {isPending && (
                            <button
                              onClick={() => handleCancelInvite(inv.id)}
                              className="px-2.5 py-1 bg-red-50 hover:bg-red-100 text-red-700 border border-red-200 rounded-lg font-semibold text-[11px] cursor-pointer"
                            >
                              Batalkan
                            </button>
                          )}
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* ================= MODAL SINGLE APPROVE ================= */}
      {approveModal.isOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-3xl max-w-sm w-full p-6 shadow-xl border border-gray-100 space-y-4">
            <h3 className="font-black text-gray-900 text-base flex items-center gap-2">
              <span>✓</span> Konfirmasi Persetujuan Akun
            </h3>
            <p className="text-xs text-gray-600 leading-relaxed">
              Apakah Anda yakin ingin menyetujui pendaftaran akun <strong>{approveModal.user?.name}</strong> ({approveModal.user?.email})?
              Akun ini akan langsung berstatus <strong className="text-emerald-700">Aktif</strong> dan notifikasi email akan segera dikirimkan.
            </p>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setApproveModal({ isOpen: false, user: null })}
                className="px-3.5 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl cursor-pointer"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={actionLoading}
                onClick={handleApprove}
                className="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs cursor-pointer"
              >
                {actionLoading ? 'Menyetujui...' : 'Ya, Setujui Akun'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ================= MODAL SINGLE REJECT ================= */}
      {rejectModal.isOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-3xl max-w-md w-full p-6 shadow-xl border border-gray-100 space-y-4">
            <h3 className="font-black text-gray-900 text-base flex items-center gap-2">
              <span>✕</span> Tolak Pendaftaran Akun
            </h3>
            <p className="text-xs text-gray-600">
              Pendaftaran untuk <strong>{rejectModal.user?.name}</strong> ({rejectModal.user?.email}) akan ditolak. Anda wajib mencantumkan alasan penolakan.
            </p>

            <form onSubmit={handleReject} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Alasan Penolakan <span className="text-rose-600">*</span>
                </label>
                <textarea
                  required
                  rows={3}
                  value={rejectModal.reason}
                  onChange={(e) => setRejectModal((prev) => ({ ...prev, reason: e.target.value }))}
                  placeholder="Misal: Data identitas tidak valid, pendaftaran ganda terdeteksi..."
                  className="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-[#1A3A5C]"
                />
                <p className="text-[11px] text-gray-400 mt-1">Alasan ini akan dikirimkan ke email pendaftar.</p>
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setRejectModal({ isOpen: false, user: null, reason: '' })}
                  className="px-3.5 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={actionLoading}
                  className="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs cursor-pointer"
                >
                  {actionLoading ? 'Menolak...' : 'Tolak Pendaftaran'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ================= MODAL BULK APPROVE ================= */}
      {bulkApproveModal.isOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-3xl max-w-sm w-full p-6 shadow-xl border border-gray-100 space-y-4">
            <h3 className="font-black text-gray-900 text-base flex items-center gap-2">
              <span>✓</span> Konfirmasi Persetujuan Massal
            </h3>
            <p className="text-xs text-gray-600 leading-relaxed">
              Anda akan menyetujui sebanyak <strong>{selectedPendingIds.length} akun pengguna</strong> sekaligus. Seluruh akun terpilih akan langsung diaktifkan dan dikirimkan email pemberitahuan.
            </p>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setBulkApproveModal({ isOpen: false })}
                className="px-3.5 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl cursor-pointer"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={actionLoading}
                onClick={handleBulkApprove}
                className="px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-xl shadow-xs cursor-pointer"
              >
                {actionLoading ? 'Menyetujui...' : `Setujui ${selectedPendingIds.length} Akun`}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ================= MODAL BULK REJECT ================= */}
      {bulkRejectModal.isOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-3xl max-w-md w-full p-6 shadow-xl border border-gray-100 space-y-4">
            <h3 className="font-black text-gray-900 text-base flex items-center gap-2">
              <span>✕</span> Penolakan Akun Massal
            </h3>
            <p className="text-xs text-gray-600">
              Anda akan menolak <strong>{selectedPendingIds.length} pendaftaran akun terpilih</strong>. Wajib mencantumkan alasan umum penolakan.
            </p>

            <form onSubmit={handleBulkReject} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Alasan Penolakan Bersama <span className="text-rose-600">*</span>
                </label>
                <textarea
                  required
                  rows={3}
                  value={bulkRejectModal.reason}
                  onChange={(e) => setBulkRejectModal((prev) => ({ ...prev, reason: e.target.value }))}
                  placeholder="Misal: Registrasi massal tidak valid, gagal verifikasi identitas..."
                  className="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-[#1A3A5C]"
                />
              </div>

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setBulkRejectModal({ isOpen: false, reason: '' })}
                  className="px-3.5 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={actionLoading}
                  className="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs cursor-pointer"
                >
                  {actionLoading ? 'Menolak...' : `Tolak ${selectedPendingIds.length} Akun`}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* ================= MODAL SOFT DELETE (2-STEP CONFIRMATION) ================= */}
      {deleteModal.isOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-3xl max-w-md w-full p-6 shadow-xl border border-gray-100 space-y-4">
            <div className="flex items-center gap-3 text-rose-600">
              <span className="text-2xl">⚠️</span>
              <h3 className="font-black text-gray-900 text-base">Hapus Akun Pengguna (Soft Delete)</h3>
            </div>

            <div className="bg-rose-50 p-3.5 rounded-2xl border border-rose-100 space-y-2 text-xs text-rose-800">
              <p className="font-bold">Konfirmasi Dua Langkah Diperlukan:</p>
              <p>
                Anda akan menghapus akun <strong>{deleteModal.user?.name}</strong> ({deleteModal.user?.email}).
              </p>
              <ul className="list-disc pl-4 space-y-1 text-[11px] text-rose-700">
                <li>Seluruh sesi login aktif akun ini akan langsung dicabut seketika.</li>
                <li>Pengguna tidak akan dapat login kembali.</li>
                <li>Data riwayat transaksi tetap tersimpan dalam sistem untuk audit.</li>
              </ul>
            </div>

            <div className="pt-2">
              <label className="flex items-start gap-2.5 cursor-pointer text-xs text-gray-700">
                <input
                  type="checkbox"
                  checked={deleteModal.confirmedStep1}
                  onChange={(e) => setDeleteModal((prev) => ({ ...prev, confirmedStep1: e.target.checked }))}
                  className="mt-0.5 rounded border-gray-300 text-rose-600 focus:ring-rose-500"
                />
                <span>Saya memahami risiko dan mengonfirmasi penghapusan akun pengguna ini.</span>
              </label>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setDeleteModal({ isOpen: false, user: null, confirmedStep1: false })}
                className="px-3.5 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl cursor-pointer"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={!deleteModal.confirmedStep1 || actionLoading}
                onClick={handleDeleteUser}
                className="px-4 py-2 text-xs font-bold text-white bg-rose-600 hover:bg-rose-700 rounded-xl shadow-xs cursor-pointer disabled:opacity-40"
              >
                {actionLoading ? 'Menghapus...' : 'Konfirmasi Hapus Akun'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ================= MODAL DETAIL USER ================= */}
      {detailModal.isOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-3xl max-w-lg w-full p-6 shadow-xl border border-gray-100 space-y-4">
            <div className="flex justify-between items-center pb-3 border-b border-gray-100">
              <h3 className="font-black text-gray-900 text-base">Detail & Ringkasan Akun</h3>
              <button
                onClick={() => setDetailModal({ isOpen: false, user: null, summary: null, loading: false })}
                className="text-gray-400 hover:text-gray-600 font-bold cursor-pointer"
              >
                ✕
              </button>
            </div>

            {detailModal.loading ? (
              <div className="py-8 text-center text-gray-400">
                <div className="w-6 h-6 border-2 border-[#1A3A5C] border-t-transparent rounded-full animate-spin mx-auto mb-2" />
                Memuat detail akun...
              </div>
            ) : detailModal.user && (
              <div className="space-y-4 text-xs">
                <div className="flex items-center gap-3 bg-gray-50 p-3.5 rounded-2xl">
                  <div className="w-12 h-12 rounded-full bg-[#1A3A5C] text-white flex items-center justify-center font-bold text-base">
                    {detailModal.user.name?.substring(0, 2) || 'PL'}
                  </div>
                  <div>
                    <h4 className="font-bold text-gray-900 text-sm">{detailModal.user.name}</h4>
                    <p className="text-gray-500">{detailModal.user.email}</p>
                    <div className="mt-1 flex gap-1">
                      {detailModal.user.roles?.map((r) => (
                        <span key={r.id || r.name} className="bg-slate-200 text-slate-800 text-[10px] px-2 py-0.5 rounded-full font-bold">
                          {r.name}
                        </span>
                      ))}
                    </div>
                  </div>
                </div>

                <div className="grid grid-cols-3 gap-2 text-center">
                  <div className="bg-blue-50 p-2.5 rounded-xl border border-blue-100">
                    <span className="block font-black text-blue-900 text-base">
                      {detailModal.summary?.order_count || 0}
                    </span>
                    <span className="text-[10px] text-blue-700 font-semibold">Total Order</span>
                  </div>
                  <div className="bg-amber-50 p-2.5 rounded-xl border border-amber-100">
                    <span className="block font-black text-amber-900 text-base">
                      {detailModal.summary?.dispute_count || 0}
                    </span>
                    <span className="text-[10px] text-amber-700 font-semibold">Komplain/Dispute</span>
                  </div>
                  <div className="bg-emerald-50 p-2.5 rounded-xl border border-emerald-100">
                    <span className="block font-black text-emerald-900 text-base">
                      {detailModal.summary?.active_sessions_count || 0}
                    </span>
                    <span className="text-[10px] text-emerald-700 font-semibold">Sesi Aktif</span>
                  </div>
                </div>

                <div className="space-y-2 pt-2 border-t border-gray-100 text-gray-600">
                  <div className="flex justify-between">
                    <span>Status Akun:</span>
                    <strong>{detailModal.user.status}</strong>
                  </div>
                  {detailModal.user.rejection_reason && (
                    <div className="flex justify-between text-rose-700">
                      <span>Alasan Penolakan:</span>
                      <strong>{detailModal.user.rejection_reason}</strong>
                    </div>
                  )}
                  <div className="flex justify-between">
                    <span>Email Verifikasi:</span>
                    <strong>{detailModal.user.email_verified_at ? 'Sudah Terverifikasi' : 'Belum Diverifikasi'}</strong>
                  </div>
                  <div className="flex justify-between">
                    <span>Terdaftar Sejak:</span>
                    <strong>{new Date(detailModal.user.created_at).toLocaleDateString('id-ID')}</strong>
                  </div>
                  <div className="flex justify-between">
                    <span>Login Terakhir:</span>
                    <strong>
                      {detailModal.user.last_login_at
                        ? new Date(detailModal.user.last_login_at).toLocaleString('id-ID')
                        : 'Belum Pernah'}
                    </strong>
                  </div>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ================= MODAL STATUS ================= */}
      {statusModal.isOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-3xl max-w-sm w-full p-6 shadow-xl border border-gray-100 space-y-4">
            <h3 className="font-black text-gray-900 text-base">
              Konfirmasi {statusModal.targetStatus === 'active' ? 'Pengaktifan' : 'Penonaktifan'} Akun
            </h3>
            <p className="text-xs text-gray-500 leading-relaxed">
              Apakah Anda yakin ingin {statusModal.targetStatus === 'active' ? 'mengaktifkan kembali' : 'menonaktifkan'} akun{' '}
              <strong>{statusModal.user?.name}</strong>?
              {statusModal.targetStatus === 'disabled' && (
                <span className="block mt-2 text-red-600 font-semibold">
                  ⚠️ Seluruh sesi login aktif pengguna ini akan langsung dicabut seketika.
                </span>
              )}
            </p>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setStatusModal({ isOpen: false, user: null, targetStatus: '' })}
                className="px-3.5 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl cursor-pointer"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={actionLoading}
                onClick={handleUpdateStatus}
                className={`px-4 py-2 text-xs font-bold text-white rounded-xl shadow-xs cursor-pointer ${
                  statusModal.targetStatus === 'disabled' ? 'bg-red-600 hover:bg-red-700' : 'bg-emerald-600 hover:bg-emerald-700'
                }`}
              >
                {actionLoading ? 'Menyimpan...' : 'Ya, Lanjutkan'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ================= MODAL UBAH ROLE ================= */}
      {/* Catatan Keamanan: Platform Owner tidak boleh diberikan lewat UI */}
      {roleModal.isOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-3xl max-w-sm w-full p-6 shadow-xl border border-gray-100 space-y-4">
            <h3 className="font-black text-gray-900 text-base">Ubah Role Pengguna</h3>
            <p className="text-xs text-gray-500">
              Pilih hak akses baru untuk <strong>{roleModal.user?.name}</strong>.
            </p>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">Role Baru:</label>
              <select
                value={roleModal.targetRole}
                onChange={(e) => setRoleModal((prev) => ({ ...prev, targetRole: e.target.value }))}
                className="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-xl text-xs font-medium focus:ring-2 focus:ring-[#1A3A5C]"
              >
                {/* Platform Owner role is INTENTIONALLY EXCLUDED from options - only via artisan bootstrap */}
                {activeRole === 'platform_owner' && (
                  <option value="admin_owner">Admin Owner</option>
                )}
                {activeRole !== 'owner_toko' && <option value="customer">Customer</option>}
                <option value="owner_toko">Owner Toko</option>
                <option value="admin_toko">Admin Toko</option>
                <option value="kurir">Kurir</option>
              </select>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setRoleModal({ isOpen: false, user: null, targetRole: '' })}
                className="px-3.5 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl cursor-pointer"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={actionLoading}
                onClick={handleUpdateRole}
                className="px-4 py-2 text-xs font-bold text-white bg-[#1A3A5C] hover:bg-[#152e4a] rounded-xl shadow-xs cursor-pointer"
              >
                {actionLoading ? 'Menyimpan...' : 'Simpan Role'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ================= MODAL CABUT SESI ================= */}
      {revokeModal.isOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-3xl max-w-sm w-full p-6 shadow-xl border border-gray-100 space-y-4">
            <h3 className="font-black text-gray-900 text-base">Cabut Seluruh Sesi Login</h3>
            <p className="text-xs text-gray-500 leading-relaxed">
              Seluruh sesi dan token aktif untuk <strong>{revokeModal.user?.name}</strong> akan langsung dihapus. Pengguna harus melakukan login ulang.
            </p>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setRevokeModal({ isOpen: false, user: null })}
                className="px-3.5 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl cursor-pointer"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={actionLoading}
                onClick={handleRevokeSessions}
                className="px-4 py-2 text-xs font-bold text-white bg-slate-800 hover:bg-black rounded-xl shadow-xs cursor-pointer"
              >
                {actionLoading ? 'Memproses...' : 'Cabut Semua Sesi'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ================= MODAL RESET PASSWORD ================= */}
      {resetModal.isOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-3xl max-w-sm w-full p-6 shadow-xl border border-gray-100 space-y-4">
            <h3 className="font-black text-gray-900 text-base">Kirim Tautan Reset Password</h3>
            <p className="text-xs text-gray-500 leading-relaxed">
              Kirimkan tautan pengaturan ulang password baru ke alamat email resmi <strong>{resetModal.user?.email}</strong>?
            </p>

            <div className="flex justify-end gap-2 pt-2">
              <button
                type="button"
                onClick={() => setResetModal({ isOpen: false, user: null })}
                className="px-3.5 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl cursor-pointer"
              >
                Batal
              </button>
              <button
                type="button"
                disabled={actionLoading}
                onClick={handleTriggerResetPassword}
                className="px-4 py-2 text-xs font-bold text-white bg-purple-700 hover:bg-purple-800 rounded-xl shadow-xs cursor-pointer"
              >
                {actionLoading ? 'Mengirim...' : 'Kirim Tautan'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ================= MODAL UNDANG STAF ================= */}
      {inviteModal.isOpen && (
        <div className="fixed inset-0 bg-black/50 backdrop-blur-xs flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-3xl max-w-md w-full p-6 shadow-xl border border-gray-100 space-y-4">
            <div className="flex justify-between items-center pb-2 border-b border-gray-100">
              <h3 className="font-black text-gray-900 text-base flex items-center gap-2">
                <span>✉️</span> Undang Staf Baru
              </h3>
              <button
                onClick={() => setInviteModal({ isOpen: false, email: '', role: 'kurir', tokoId: '' })}
                className="text-gray-400 hover:text-gray-600 font-bold cursor-pointer"
              >
                ✕
              </button>
            </div>

            <form onSubmit={handleSendInvite} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Alamat Email Calon Staf
                </label>
                <input
                  type="email"
                  required
                  placeholder="staf@contoh.com"
                  value={inviteModal.email}
                  onChange={(e) => setInviteModal((prev) => ({ ...prev, email: e.target.value }))}
                  className="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-xs focus:ring-2 focus:ring-[#1A3A5C]"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Posisi / Role Ditugaskan
                </label>
                <select
                  value={inviteModal.role}
                  onChange={(e) => setInviteModal((prev) => ({ ...prev, role: e.target.value }))}
                  className="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-xl text-xs font-medium focus:ring-2 focus:ring-[#1A3A5C]"
                >
                  {activeRole === 'platform_owner' && (
                    <option value="admin_owner">Admin Owner (Moderasi Platform)</option>
                  )}
                  <option value="admin_toko">Admin Toko (Operasional Cucian)</option>
                  <option value="kurir">Mitra Kurir (Jemput & Antar)</option>
                </select>
              </div>

              {/* Pilihan Toko jika mengundang Admin Toko atau Kurir */}
              {(inviteModal.role === 'admin_toko' || inviteModal.role === 'kurir') && myTokos.length > 0 && (
                <div>
                  <label className="block text-xs font-bold text-gray-700 mb-1">
                    Toko Laundry Penugasan
                  </label>
                  <select
                    value={inviteModal.tokoId}
                    onChange={(e) => setInviteModal((prev) => ({ ...prev, tokoId: e.target.value }))}
                    className="w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-xl text-xs font-medium focus:ring-2 focus:ring-[#1A3A5C]"
                  >
                    {myTokos.map((toko) => (
                      <option key={toko.id} value={toko.id}>
                        {toko.nama} - {toko.alamat}
                      </option>
                    ))}
                  </select>
                </div>
              )}

              <div className="flex justify-end gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setInviteModal({ isOpen: false, email: '', role: 'kurir', tokoId: '' })}
                  className="px-3.5 py-2 text-xs font-bold text-gray-600 hover:bg-gray-100 rounded-xl cursor-pointer"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={actionLoading}
                  className="px-4 py-2 text-xs font-bold text-white bg-[#1A3A5C] hover:bg-[#152e4a] rounded-xl shadow-xs cursor-pointer"
                >
                  {actionLoading ? 'Mengirim Undangan...' : 'Kirim Undangan Email'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
