import { useState, useEffect } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import api from '../../api/client';

export default function AcceptInvitation() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const { login, changeActiveRole } = useAuth();

  const token = searchParams.get('token') || '';

  const [invitation, setInvitation] = useState(null);
  const [fetchingDetails, setFetchingDetails] = useState(true);
  const [name, setName] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    if (!token) {
      setFetchingDetails(false);
      setError('Token undangan tidak ditemukan pada URL tautan.');
      return;
    }

    const fetchDetails = async () => {
      try {
        setFetchingDetails(true);
        const res = await api.get(`/invitations/details?token=${encodeURIComponent(token)}`);
        setInvitation(res);
      } catch (err) {
        setError(err.message || 'Tautan undangan tidak valid, telah kedaluwarsa, atau sudah pernah digunakan.');
      } finally {
        setFetchingDetails(false);
      }
    };

    fetchDetails();
  }, [token]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);

    if (password.length < 8) {
      setError('Password minimal harus 8 karakter.');
      return;
    }

    if (password !== passwordConfirmation) {
      setError('Konfirmasi password tidak cocok.');
      return;
    }

    setLoading(true);

    try {
      const res = await api.post('/invitations/accept', {
        token,
        name: name.trim(),
        password,
        password_confirmation: passwordConfirmation,
      });

      // Login otomatis dengan data kredensial baru
      login(res.token, res.user, res.roles);
      if (res.active_role) {
        changeActiveRole(res.active_role);
      }

      const rolePath = res.active_role === 'admin_owner' ? '/admin-owner' :
        res.active_role === 'admin_toko' ? '/admin-toko' :
        res.active_role === 'kurir' ? '/kurir' : '/';

      navigate(rolePath);
    } catch (err) {
      setError(err.message || 'Gagal menerima undangan. Silakan periksa kembali formulir Anda.');
    } finally {
      setLoading(false);
    }
  };

  const getRoleLabel = (role) => {
    switch (role) {
      case 'admin_owner': return 'Admin Owner (Platform)';
      case 'admin_toko': return 'Admin Toko (Staff Cucian)';
      case 'kurir': return 'Mitra Kurir (Penjemputan & Pengantaran)';
      default: return role;
    }
  };

  return (
    <div className="min-h-screen bg-neutral flex items-center justify-center p-4 py-8">
      <div className="bg-white rounded-3xl shadow-xl max-w-md w-full p-6 sm:p-8 border border-gray-100">
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-14 h-14 bg-[#1A3A5C] text-white rounded-2xl mb-3 shadow-md">
            <span className="text-2xl">🤝</span>
          </div>
          <h1 className="text-2xl font-black text-[#1A3A5C] tracking-tight">Undangan Bergabung</h1>
          <p className="text-xs text-gray-500 mt-1">
            Lengkapi data akun Anda untuk mulai bertugas di Pusat Laundry
          </p>
        </div>

        {fetchingDetails ? (
          <div className="text-center py-8 space-y-3">
            <div className="w-8 h-8 border-4 border-[#1A3A5C] border-t-transparent rounded-full animate-spin mx-auto" />
            <p className="text-xs text-gray-500">Memeriksa keaslian tautan undangan...</p>
          </div>
        ) : error ? (
          <div className="space-y-4">
            <div className="p-4 bg-red-50 border border-red-200 text-red-700 text-xs rounded-2xl">
              <p className="font-bold mb-1">Undangan Tidak Valid</p>
              <p>{error}</p>
            </div>
            <Link
              to="/login"
              className="w-full inline-flex items-center justify-center bg-[#1A3A5C] text-white py-3 px-4 rounded-xl font-bold text-sm hover:bg-[#152e4a] transition text-center shadow-md cursor-pointer"
            >
              Kembali ke Halaman Login
            </Link>
          </div>
        ) : (
          <div>
            {/* Kartu Ringkasan Undangan */}
            <div className="bg-slate-50 border border-slate-200 rounded-2xl p-4 mb-5 text-xs text-slate-700 space-y-2">
              <div className="flex justify-between items-center pb-2 border-b border-slate-200">
                <span className="text-gray-500">Email:</span>
                <span className="font-bold text-gray-900">{invitation?.email}</span>
              </div>
              <div className="flex justify-between items-center pb-2 border-b border-slate-200">
                <span className="text-gray-500">Posisi / Role:</span>
                <span className="font-bold text-[#1A3A5C] bg-blue-100 px-2 py-0.5 rounded-full">
                  {getRoleLabel(invitation?.role)}
                </span>
              </div>
              {invitation?.toko && (
                <div className="flex justify-between items-center pb-2 border-b border-slate-200">
                  <span className="text-gray-500">Toko Laundry:</span>
                  <span className="font-bold text-gray-900">{invitation?.toko?.nama}</span>
                </div>
              )}
              <div className="flex justify-between items-center text-gray-500 text-[11px]">
                <span>Diundang oleh:</span>
                <span className="font-medium text-gray-700">{invitation?.invited_by?.name || 'Administrator'}</span>
              </div>
            </div>

            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Nama Lengkap Anda
                </label>
                <input
                  type="text"
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="Masukkan nama lengkap Anda"
                  className="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-[#1A3A5C] focus:bg-white focus:outline-none transition"
                  autoComplete="name"
                />
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Buat Password Akun (Min. 8 Karakter)
                </label>
                <div className="relative">
                  <input
                    type={showPassword ? 'text' : 'password'}
                    required
                    value={password}
                    onChange={(e) => setPassword(e.target.value)}
                    placeholder="••••••••"
                    className="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-[#1A3A5C] focus:bg-white focus:outline-none transition pr-10"
                    autoComplete="new-password"
                  />
                  <button
                    type="button"
                    onClick={() => setShowPassword(!showPassword)}
                    className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 text-xs font-medium cursor-pointer"
                  >
                    {showPassword ? 'Sembunyikan' : 'Lihat'}
                  </button>
                </div>
              </div>

              <div>
                <label className="block text-xs font-bold text-gray-700 mb-1">
                  Ulangi Password
                </label>
                <input
                  type={showPassword ? 'text' : 'password'}
                  required
                  value={passwordConfirmation}
                  onChange={(e) => setPasswordConfirmation(e.target.value)}
                  placeholder="••••••••"
                  className="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-[#1A3A5C] focus:bg-white focus:outline-none transition"
                  autoComplete="new-password"
                />
              </div>

              <button
                type="submit"
                disabled={loading}
                className="w-full bg-[#1A3A5C] text-white py-3 px-4 rounded-xl font-bold text-sm hover:bg-[#152e4a] active:scale-[0.99] transition shadow-md cursor-pointer disabled:opacity-60 flex items-center justify-center gap-2 mt-2"
              >
                {loading ? (
                  <>
                    <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    <span>Menerima Undangan...</span>
                  </>
                ) : (
                  'Terima Undangan & Mulai Bertugas'
                )}
              </button>
            </form>
          </div>
        )}
      </div>
    </div>
  );
}
