import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import api from '../../api/client';

export default function Register() {
  const navigate = useNavigate();

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [successData, setSuccessData] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);

    if (password.length < 8) {
      setError('Password minimal harus 8 karakter.');
      return;
    }

    if (password !== passwordConfirmation) {
      setError('Konfirmasi password tidak cocok dengan password.');
      return;
    }

    setLoading(true);

    try {
      const res = await api.post('/auth/register', {
        name: name.trim(),
        email: email.trim(),
        password,
        password_confirmation: passwordConfirmation,
      });

      // Pendaftaran berhasil: tampilkan layar panduan verifikasi email
      setSuccessData({
        email: email.trim(),
        message: res.message || 'Tautan verifikasi telah dikirim ke email Anda.',
      });
    } catch (err) {
      if (err.errors) {
        const firstErrorKey = Object.keys(err.errors)[0];
        setError(err.errors[firstErrorKey][0]);
      } else {
        setError(err.message || 'Pendaftaran gagal. Periksa kembali data Anda.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-neutral flex items-center justify-center p-4 py-8">
      <div className="bg-white rounded-3xl shadow-xl max-w-md w-full p-6 sm:p-8 border border-gray-100">
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-14 h-14 bg-[#1A3A5C] text-white rounded-2xl mb-3 shadow-md">
            <span className="text-2xl">🧺</span>
          </div>
          <h1 className="text-2xl font-black text-[#1A3A5C] tracking-tight">Daftar Akun Baru</h1>
          <p className="text-xs text-gray-500 mt-1">
            Bergabung sebagai Pelanggan Pusat Laundry
          </p>
        </div>

        {error && (
          <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-center gap-2">
            <span>⚠️</span>
            <span>{error}</span>
          </div>
        )}

        {successData ? (
          <div className="space-y-4">
            <div className="p-4 bg-blue-50 border border-blue-200 text-blue-950 rounded-2xl text-xs sm:text-sm leading-relaxed space-y-2">
              <div className="flex items-center gap-2 font-bold text-blue-900 text-sm">
                <span>📧</span>
                <span>Cek Email Anda untuk Verifikasi</span>
              </div>
              <p>
                Pendaftaran berhasil! Kami telah mengirimkan tautan verifikasi ke <strong>{successData.email}</strong>.
              </p>
              <div className="bg-white/80 p-3 rounded-xl border border-blue-100 text-[11px] text-gray-700 space-y-1">
                <p className="font-semibold text-blue-900">Alur Aktivasi Akun:</p>
                <ol className="list-decimal list-inside space-y-0.5">
                  <li>Buka kotak masuk (inbox) atau folder spam email Anda.</li>
                  <li>Klik tombol atau tautan verifikasi yang kami kirimkan.</li>
                  <li>Akun Anda akan ditinjau dan disetujui oleh admin platform sebelum dapat digunakan.</li>
                </ol>
              </div>
            </div>

            <Link
              to="/login"
              className="w-full inline-flex items-center justify-center bg-[#1A3A5C] text-white py-3 px-4 rounded-xl font-bold text-sm hover:bg-[#152e4a] transition text-center shadow-md cursor-pointer"
            >
              Kembali ke Halaman Login
            </Link>
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">
                Nama Lengkap
              </label>
              <input
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Budi Santoso"
                className="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-[#1A3A5C] focus:bg-white focus:outline-none transition"
                autoComplete="name"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">
                Email
              </label>
              <input
                type="email"
                required
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                placeholder="budi@contoh.com"
                className="w-full px-3.5 py-2.5 bg-gray-50 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-[#1A3A5C] focus:bg-white focus:outline-none transition"
                autoComplete="email"
              />
            </div>

            <div>
              <label className="block text-xs font-bold text-gray-700 mb-1">
                Password (Minimal 8 Karakter)
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
                  <span>Mendaftarkan...</span>
                </>
              ) : (
                'Daftar Sekarang'
              )}
            </button>
          </form>
        )}

        <div className="text-center mt-6">
          <p className="text-xs text-gray-600">
            Sudah memiliki akun?{' '}
            <Link to="/login" className="font-bold text-[#1A3A5C] hover:underline">
              Masuk di sini
            </Link>
          </p>
        </div>
      </div>
    </div>
  );
}
