import { useEffect, useState } from 'react';
import api from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';

export default function TokoApprovalDashboard() {
  const { activeRole } = useAuth();
  const [tokos, setTokos] = useState([]);
  const [statusFilter, setStatusFilter] = useState('');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // State Modal Aksi Status
  const [actionModal, setActionModal] = useState({
    isOpen: false,
    toko: null,
    targetStatus: '',
    reason: '',
  });
  const [processing, setProcessing] = useState(false);

  // State simulasi test Finance Access (Platform vs Admin Owner)
  const [financeTestResult, setFinanceTestResult] = useState(null);
  const [testingFinance, setTestingFinance] = useState(false);

  const fetchTokos = async () => {
    setLoading(true);
    try {
      const url = statusFilter ? `/platform/tokos?status=${statusFilter}` : '/platform/tokos';
      const res = await api.get(url);
      setTokos(res.data || []);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchTokos();
  }, [statusFilter]);

  const handleOpenActionModal = (toko, targetStatus) => {
    setActionModal({
      isOpen: true,
      toko,
      targetStatus,
      reason: '',
    });
  };

  const handleExecuteStatusChange = async (e) => {
    e.preventDefault();
    if (!actionModal.toko) return;
    setProcessing(true);
    try {
      const res = await api.patch(`/platform/tokos/${actionModal.toko.id}/status`, {
        status: actionModal.targetStatus,
        reason: actionModal.reason,
      });

      alert(`Sukses: ${res.message} (Audit Log ID: #${res.audit_log_id})`);
      setActionModal({ isOpen: false, toko: null, targetStatus: '', reason: '' });
      fetchTokos();
    } catch (err) {
      alert('Gagal mengubah status: ' + err.message);
    } finally {
      setProcessing(false);
    }
  };

  // Uji coba Policy Keuangan Inti (FinancePolicy)
  const testFinanceAccess = async () => {
    setTestingFinance(true);
    setFinanceTestResult(null);
    try {
      const res = await api.get('/platform/finances/commission');
      setFinanceTestResult({
        success: true,
        data: res,
      });
    } catch (err) {
      setFinanceTestResult({
        success: false,
        message: err.message,
      });
    } finally {
      setTestingFinance(false);
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

  return (
    <div className="max-w-6xl mx-auto space-y-6">
      {/* Header */}
      <div className="bg-white p-6 rounded-xl border border-gray-200 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-primary">Persetujuan & Moderasi Toko</h1>
          <p className="text-sm text-gray-500 mt-1">
            Verifikasi pendaftaran toko baru, dokumen legalitas, dan status moderasi.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <span className="text-xs bg-primary/10 text-primary font-semibold px-3 py-1.5 rounded-lg border border-primary/20">
            Aktif: {activeRole}
          </span>
        </div>
      </div>

      {/* Info Pemisahan Wewenang FinancePolicy */}
      <div className="bg-blue-50 border border-blue-200 p-4 rounded-xl flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-sm text-blue-900">
        <div>
          <p className="font-bold">🛡️ Isolasi Wewenang Platform (FinancePolicy):</p>
          <p className="text-xs text-blue-700 mt-0.5">
            Admin Owner berhak meng-approve toko, namun <strong>TIDAK BISA</strong> mengubah komisi platform atau mencairkan settlement dana.
          </p>
        </div>
        <button
          onClick={testFinanceAccess}
          disabled={testingFinance}
          className="self-start sm:self-auto px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold rounded-lg transition-colors"
        >
          {testingFinance ? 'Menguji...' : 'Test Akses Keuangan'}
        </button>
      </div>

      {financeTestResult && (
        <div className={`p-4 rounded-xl text-xs border ${financeTestResult.success ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-rose-50 border-rose-200 text-rose-800'}`}>
          <p className="font-bold mb-1">
            Hasil Tes Hak Akses Keuangan ({activeRole}):
          </p>
          {financeTestResult.success ? (
            <p>✅ Diizinkan (Platform Owner memiliki permission <code>manage_finances</code>).</p>
          ) : (
            <p>❌ Ditolak oleh <code>FinancePolicy</code>: {financeTestResult.message} (Admin Owner tidak memiliki permission <code>manage_finances</code>).</p>
          )}
        </div>
      )}

      {/* Filter Status */}
      <div className="flex gap-2 flex-wrap items-center bg-white p-3 rounded-xl border border-gray-200 shadow-sm text-xs font-medium">
        <span className="text-gray-500 mr-2">Filter Status:</span>
        {['', 'submitted', 'approved', 'rejected', 'suspended'].map((st) => (
          <button
            key={st}
            onClick={() => setStatusFilter(st)}
            className={`px-3 py-1.5 rounded-lg transition-colors ${
              statusFilter === st
                ? 'bg-primary text-white font-bold'
                : 'bg-neutral text-gray-700 hover:bg-gray-200'
            }`}
          >
            {st === '' ? 'Semua' : st.toUpperCase()}
          </button>
        ))}
      </div>

      {error && (
        <div className="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-sm">
          {error}
        </div>
      )}

      {/* Tabel Pengajuan Toko */}
      <div className="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        {loading ? (
          <div className="p-12 text-center text-gray-400">
            <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin mx-auto mb-2" />
            Memuat daftar toko...
          </div>
        ) : tokos.length === 0 ? (
          <div className="p-12 text-center text-gray-400 text-sm">
            Tidak ada data toko dengan filter status ini.
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="bg-neutral text-xs font-semibold text-gray-500 uppercase border-b border-gray-200">
                <tr>
                  <th className="py-3.5 px-4">Toko & Pemilik</th>
                  <th className="py-3.5 px-4">Alamat & Lokasi</th>
                  <th className="py-3.5 px-4">Dokumen</th>
                  <th className="py-3.5 px-4">Status</th>
                  <th className="py-3.5 px-4 text-right">Aksi Moderasi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-gray-100">
                {tokos.map((toko) => (
                  <tr key={toko.id} className="hover:bg-gray-50 transition-colors">
                    <td className="py-3.5 px-4">
                      <div className="font-bold text-gray-900">{toko.nama}</div>
                      <div className="text-xs text-gray-500 mt-0.5">
                        Owner: {toko.owner?.name} ({toko.owner?.email})
                      </div>
                    </td>
                    <td className="py-3.5 px-4 max-w-xs">
                      <p className="text-xs text-gray-700 line-clamp-2">{toko.alamat}</p>
                      {toko.latitude && (
                        <p className="text-xs text-gray-400 mt-0.5">
                          📍 {toko.latitude.toFixed(4)}, {toko.longitude.toFixed(4)}
                        </p>
                      )}
                    </td>
                    <td className="py-3.5 px-4">
                      {toko.dokumen_legalitas ? (
                        <span className="text-xs text-secondary font-medium bg-secondary/10 px-2 py-1 rounded">
                          📄 Terunggah
                        </span>
                      ) : (
                        <span className="text-xs text-gray-400 italic">Belum diunggah</span>
                      )}
                    </td>
                    <td className="py-3.5 px-4">
                      {getStatusBadge(toko.status)}
                    </td>
                    <td className="py-3.5 px-4 text-right">
                      <div className="flex items-center justify-end gap-1.5 flex-wrap">
                        {toko.status !== 'approved' && (
                          <button
                            onClick={() => handleOpenActionModal(toko, 'approved')}
                            className="bg-emerald-600 hover:bg-emerald-700 text-white text-xs px-2.5 py-1 rounded font-medium transition-colors"
                          >
                            Approve
                          </button>
                        )}
                        {toko.status !== 'rejected' && (
                          <button
                            onClick={() => handleOpenActionModal(toko, 'rejected')}
                            className="bg-rose-600 hover:bg-rose-700 text-white text-xs px-2.5 py-1 rounded font-medium transition-colors"
                          >
                            Reject
                          </button>
                        )}
                        {toko.status !== 'suspended' && (
                          <button
                            onClick={() => handleOpenActionModal(toko, 'suspended')}
                            className="bg-gray-600 hover:bg-gray-700 text-white text-xs px-2.5 py-1 rounded font-medium transition-colors"
                          >
                            Suspend
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

      {/* Modal Aksi Moderasi & Alasan */}
      {actionModal.isOpen && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
          <div className="bg-white rounded-2xl max-w-md w-full p-6 space-y-4 shadow-xl">
            <h3 className="text-lg font-bold text-gray-900">
              Konfirmasi Ubah Status Toko
            </h3>
            <p className="text-sm text-gray-600">
              Anda akan mengubah status toko <strong>"{actionModal.toko?.nama}"</strong> menjadi{' '}
              <span className="font-bold uppercase text-primary">{actionModal.targetStatus}</span>.
            </p>
            <p className="text-xs text-amber-700 bg-amber-50 p-2.5 rounded-lg border border-amber-200">
              ⚠️ Aksi ini akan dicatat secara permanen di <strong>audit_logs</strong> (immutable trail).
            </p>

            <form onSubmit={handleExecuteStatusChange} className="space-y-4">
              <div>
                <label className="block text-xs font-semibold text-gray-700 mb-1">
                  Catatan / Alasan (Opsional untuk Approve, Dianjurkan jika Reject/Suspend):
                </label>
                <textarea
                  rows="3"
                  value={actionModal.reason}
                  onChange={(e) => setActionModal({ ...actionModal, reason: e.target.value })}
                  placeholder="Contoh: Dokumen legalitas valid dan alamat terverifikasi..."
                  className="w-full border border-gray-300 rounded-lg p-2.5 text-xs focus:ring-2 focus:ring-secondary focus:border-secondary"
                ></textarea>
              </div>

              <div className="flex gap-2 justify-end pt-2">
                <button
                  type="button"
                  onClick={() => setActionModal({ isOpen: false, toko: null, targetStatus: '', reason: '' })}
                  className="px-4 py-2 border border-gray-300 text-xs font-medium rounded-lg text-gray-700 hover:bg-gray-50"
                >
                  Batal
                </button>
                <button
                  type="submit"
                  disabled={processing}
                  className="px-4 py-2 bg-primary hover:bg-primary-dark text-white text-xs font-semibold rounded-lg shadow disabled:opacity-50"
                >
                  {processing ? 'Menyimpan...' : 'Simpan Perubahan'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
