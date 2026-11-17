import { useState } from 'react';
import { useNavigate, Link, useLocation } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import api from '../../api/client';

export default function Login() {
  const navigate = useNavigate();
  const location = useLocation();
  const { login, changeActiveRole } = useAuth();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [successNotice, setSuccessNotice] = useState(location.state?.message || null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    setSuccessNotice(null);
    setLoading(true);

    try {
      const res = await api.post('/auth/login', {
        email: email.trim(),
        password,
      });

      // 1. Cek jika akun mewajibkan verifikasi 2FA (Platform Owner & Admin Owner)
      if (res.requires_2fa) {
        // Simpan pending token di state/localStorage
        login(res.token);
        // Arahkan ke /2fa/setup jika belum punya secret terkonfirmasi, atau ke /2fa/challenge jika sudah
        const targetPath = res.two_factor_destination || (res.two_factor_step === 'setup' ? '/2fa/setup' : '/2fa/challenge');
        navigate(targetPath);
        return;
      }

      // 2. Login standar Sanctum
      login(res.token, res.user, res.roles);
      if (res.active_role) {
        changeActiveRole(res.active_role);
      }

      // Arahkan ke rute awal atau dashboard sesuai role
      const defaultPath = res.active_role === 'customer' ? '/customer' :
        res.active_role === 'owner_toko' ? '/owner-toko' :
        res.active_role === 'admin_toko' ? '/admin-toko' :
        res.active_role === 'kurir' ? '/kurir' :
        res.active_role === 'admin_owner' ? '/admin-owner' :
        res.active_role === 'platform_owner' ? '/platform-owner' : '/';

      navigate(defaultPath);
    } catch (err) {
      setError(err.message || 'Email atau password yang Anda masukkan salah.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-neutral flex items-center justify-center p-4 py-8">
      <div className="bg-white rounded-3xl shadow-xl max-w-md w-full p-6 sm:p-8 border border-gray-100">
        {/* Logo & Header */}
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-16 h-16 bg-[#1A3A5C] text-white rounded-2xl mb-3 shadow-md">
            <span className="text-3xl">🧺</span>
          </div>
          <h1 className="text-2xl font-black text-[#1A3A5C] tracking-tight">Pusat Laundry</h1>
          <p className="text-xs text-gray-500 mt-1">Platform Marketplace Laundry On-Demand Terpercaya</p>
        </div>

        {successNotice && (
          <div className="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center gap-2">
            <span>✅</span>
            <span>{successNotice}</span>
          </div>
        )}

        {error && (
          <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-center gap-2">
            <span>⚠️</span>
            <span>{error}</span>
          </div>
        )}

        {/* Form Login Email + Password */}
        <form onSubmit={handleSubmit} className="space-y-4 mb-6">
          <div>
            <label className="block text-xs font-bold text-gray-700 mb-1">
              Email
            </label>
            <input
              type="email"
              required
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="nama@email.com"
              className="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-[#1A3A5C] focus:bg-white focus:outline-none transition"
              autoComplete="email"
            />
          </div>

          <div>
            <div className="flex items-center justify-between mb-1">
              <label className="block text-xs font-bold text-gray-700">
                Password
              </label>
              <Link
                to="/forgot-password"
                className="text-xs text-[#1A3A5C] hover:underline font-semibold"
              >
                Lupa Password?
              </Link>
            </div>
            <div className="relative">
              <input
                type={showPassword ? 'text' : 'password'}
                required
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                placeholder="••••••••"
                className="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-[#1A3A5C] focus:bg-white focus:outline-none transition pr-10"
                autoComplete="current-password"
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

          <button
            type="submit"
            disabled={loading}
            className="w-full bg-[#1A3A5C] text-white py-3 px-4 rounded-xl font-bold text-sm hover:bg-[#152e4a] active:scale-[0.99] transition shadow-md cursor-pointer disabled:opacity-60 flex items-center justify-center gap-2"
          >
            {loading ? (
              <>
                <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                <span>Memproses...</span>
              </>
            ) : (
              'Masuk ke Akun'
            )}
          </button>
        </form>

        <div className="text-center pt-2 border-t border-gray-100">
          <p className="text-xs text-gray-600">
            Belum punya akun?{' '}
            <Link to="/register" className="font-bold text-[#1A3A5C] hover:underline">
              Daftar sebagai Customer
            </Link>
          </p>
        </div>

        <p className="text-[11px] text-gray-400 text-center mt-6">
          &copy; {new Date().getFullYear()} Pusat Laundry Indonesia. Hak Cipta Dilindungi.
        </p>
      </div>
    </div>
  );
}
