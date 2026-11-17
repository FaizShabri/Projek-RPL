import { useState } from 'react';
import { useNavigate, useSearchParams, Link } from 'react-router-dom';
import api from '../../api/client';

export default function ResetPassword() {
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();

  const token = searchParams.get('token') || '';
  const email = searchParams.get('email') || '';

  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);

    if (!token) {
      setError('Token reset password tidak valid atau tidak ditemukan pada URL.');
      return;
    }

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
      await api.post('/auth/reset-password', {
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });

      // Berhasil direset -> Arahkan ke halaman login
      navigate('/login', {
        state: {
          message: 'Password Anda berhasil diperbarui! Silakan masuk dengan password baru Anda.',
        },
      });
    } catch (err) {
      setError(err.message || 'Tautan reset password sudah kedaluwarsa atau tidak valid.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-neutral flex items-center justify-center p-4 py-8">
      <div className="bg-white rounded-3xl shadow-xl max-w-md w-full p-6 sm:p-8 border border-gray-100">
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-14 h-14 bg-[#1A3A5C] text-white rounded-2xl mb-3 shadow-md">
            <span className="text-2xl">🔑</span>
          </div>
          <h1 className="text-2xl font-black text-[#1A3A5C] tracking-tight">Atur Ulang Password</h1>
          <p className="text-xs text-gray-500 mt-1">
            Masukkan password baru yang aman untuk akun <strong>{email || 'Anda'}</strong>
          </p>
        </div>

        {error && (
          <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-center gap-2">
            <span>⚠️</span>
            <span>{error}</span>
          </div>
        )}

        {!token ? (
          <div className="text-center py-4 space-y-4">
            <p className="text-xs text-red-600 font-medium">
              Tautan reset tidak memiliki token yang valid. Harap gunakan tautan dari email Anda.
            </p>
            <Link
              to="/forgot-password"
              className="inline-block text-xs text-[#1A3A5C] font-bold underline"
            >
              Minta Tautan Baru
            </Link>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">
                Password Baru (Min. 8 Karakter)
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
                Ulangi Password Baru
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
                  <span>Menyimpan Password...</span>
                </>
              ) : (
                'Simpan Password Baru'
              )}
            </button>
          </form>
        )}
      </div>
    </div>
  );
}
