import { useState } from 'react';
import { useAuth } from '../../contexts/AuthContext';
import api from '../../api/client';

export default function VerifyEmailBanner() {
  const { user, isAuthenticated } = useAuth();
  const [resending, setResending] = useState(false);
  const [sentMessage, setSentMessage] = useState(null);
  const [errorMessage, setErrorMessage] = useState(null);

  // Jangan tampilkan jika belum login atau email sudah terverifikasi
  if (!isAuthenticated || !user || user.email_verified_at) {
    return null;
  }

  const handleResend = async () => {
    try {
      setResending(true);
      setSentMessage(null);
      setErrorMessage(null);

      const res = await api.post('/auth/email/resend');
      setSentMessage(res.message || 'Tautan verifikasi baru telah dikirim ke email Anda.');
    } catch (err) {
      setErrorMessage(err.message || 'Gagal mengirim ulang tautan verifikasi. Coba beberapa saat lagi.');
    } finally {
      setResending(false);
    }
  };

  return (
    <aside aria-label="Peringatan Verifikasi Email" className="bg-amber-500 text-amber-950 px-4 py-2.5 text-xs sm:text-sm font-medium border-b border-amber-600 shadow-xs">
      <div className="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-2">
        <div className="flex items-center gap-2 text-center sm:text-left">
          <span className="text-base">📧</span>
          <span>
            Email Anda <strong>({user.email})</strong> belum diverifikasi. Verifikasi wajib untuk membuat pesanan dan mengelola toko.
          </span>
        </div>

        <div className="flex items-center gap-3">
          {sentMessage ? (
            <span className="text-white bg-amber-800/80 px-2.5 py-1 rounded-md text-xs font-semibold">
              ✓ {sentMessage}
            </span>
          ) : errorMessage ? (
            <span className="text-red-900 bg-red-100 px-2.5 py-1 rounded-md text-xs font-semibold">
              {errorMessage}
            </span>
          ) : (
            <button
              onClick={handleResend}
              disabled={resending}
              className="bg-white text-amber-900 font-bold px-3 py-1 rounded-md hover:bg-amber-50 active:scale-95 transition cursor-pointer text-xs shadow-2xs disabled:opacity-60"
            >
              {resending ? 'Mengirim...' : 'Kirim Ulang Email'}
            </button>
          )}
        </div>
      </div>
    </aside>
  );
}
