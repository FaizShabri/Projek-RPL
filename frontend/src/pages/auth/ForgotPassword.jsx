import { useState } from 'react';
import { Link } from 'react-router-dom';
import api from '../../api/client';

export default function ForgotPassword() {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setError(null);
    setLoading(true);

    try {
      await api.post('/auth/forgot-password', {
        email: email.trim(),
      });
      // Response selalu generik demi keamanan (tidak membocorkan apakah email terdaftar)
      setSubmitted(true);
    } catch (err) {
      setError(err.message || 'Terjadi kesalahan sistem. Silakan coba lagi.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-neutral flex items-center justify-center p-4 py-8">
      <div className="bg-white rounded-3xl shadow-xl max-w-md w-full p-6 sm:p-8 border border-gray-100">
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-14 h-14 bg-[#1A3A5C] text-white rounded-2xl mb-3 shadow-md">
            <span className="text-2xl">🔐</span>
          </div>
          <h1 className="text-2xl font-black text-[#1A3A5C] tracking-tight">Lupa Password?</h1>
          <p className="text-xs text-gray-500 mt-1">
            Masukkan email terdaftar Anda untuk menerima tautan pengaturan ulang password.
          </p>
        </div>

        {error && (
          <div className="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-center gap-2">
            <span>⚠️</span>
            <span>{error}</span>
          </div>
        )}

        {submitted ? (
          <div className="space-y-4">
            <div className="p-4 bg-emerald-50 border border-emerald-200 text-emerald-900 rounded-2xl text-xs sm:text-sm leading-relaxed">
              <p className="font-bold mb-1">Tautan Telah Dikirim</p>
              <p>
                Jika email <strong>{email}</strong> terdaftar di sistem Pusat Laundry, kami telah mengirimkan tautan untuk mengatur ulang password Anda. Tautan berlaku selama 60 menit.
              </p>
              <p className="mt-2 text-emerald-700 text-xs">
                Periksa kotak masuk (inbox) atau folder spam email Anda.
              </p>
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
                Alamat Email
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

            <button
              type="submit"
              disabled={loading}
              className="w-full bg-[#1A3A5C] text-white py-3 px-4 rounded-xl font-bold text-sm hover:bg-[#152e4a] active:scale-[0.99] transition shadow-md cursor-pointer disabled:opacity-60 flex items-center justify-center gap-2"
            >
              {loading ? (
                <>
                  <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                  <span>Mengirim Tautan...</span>
                </>
              ) : (
                'Kirim Tautan Reset Password'
              )}
            </button>

            <div className="text-center pt-2">
              <Link to="/login" className="text-xs text-gray-500 hover:text-gray-800 font-semibold">
                ← Kembali ke Login
              </Link>
            </div>
          </form>
        )}
      </div>
    </div>
  );
}
