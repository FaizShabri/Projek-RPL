import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';

export default function TwoFactorChallenge() {
  const [useRecoveryCode, setUseRecoveryCode] = useState(false);
  const [otp, setOtp] = useState('');
  const [recoveryCode, setRecoveryCode] = useState('');
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);
  const [verifying, setVerifying] = useState(false);

  const navigate = useNavigate();
  const { login, logout, changeActiveRole } = useAuth();

  const hasCheckedRef = useRef(false);

  // Periksa apakah user sebenarnya butuh setup (belum punya secret/confirmed)
  useEffect(() => {
    if (hasCheckedRef.current) return;
    hasCheckedRef.current = true;

    const checkStatus = async () => {
      try {
        setLoading(true);
        const statusRes = await api.get('/auth/2fa/status');
        if (!statusRes.is_confirmed) {
          navigate('/2fa/setup', { replace: true });
        }
      } catch (err) {
        // Jika unauthenticated atau token kedaluwarsa, tampilkan pesan ramah
        setError(err.message || 'Sesi login 2FA Anda telah kedaluwarsa. Silakan login kembali.');
      } finally {
        setLoading(false);
      }
    };

    checkStatus();
  }, [navigate]);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setVerifying(true);
    setError(null);

    try {
      const payload = useRecoveryCode
        ? { recovery_code: recoveryCode.trim().toUpperCase() }
        : { otp: otp.trim() };

      const response = await api.post('/auth/2fa/verify', payload);

      // Simpan token baru dengan hak akses penuh
      login(response.token, response.user, response.roles);
      if (response.active_role) {
        changeActiveRole(response.active_role);
      }

      const defaultPath = response.active_role === 'platform_owner' ? '/platform-owner' :
        response.active_role === 'admin_owner' ? '/admin-owner' : '/';

      navigate(defaultPath, { replace: true });
    } catch (err) {
      setError(err.message || 'Kode verifikasi salah atau tidak valid.');
    } finally {
      setVerifying(false);
    }
  };

  const handleCancel = () => {
    logout();
    navigate('/login', { replace: true });
  };

  return (
    <div className="min-h-screen bg-neutral flex items-center justify-center p-4">
      <div className="bg-white rounded-3xl shadow-xl max-w-sm w-full p-6 sm:p-8 text-center border border-gray-100">
        <div className="inline-flex items-center justify-center w-14 h-14 bg-[#1A3A5C] text-white rounded-2xl mb-4 shadow-md">
          <span className="text-2xl">{useRecoveryCode ? '🔑' : '🛡️'}</span>
        </div>

        <h1 className="text-xl font-black text-[#1A3A5C] mb-1">
          {useRecoveryCode ? 'Gunakan Kode Pemulihan' : 'Verifikasi Dua Langkah (2FA)'}
        </h1>
        <p className="text-xs text-gray-500 mb-5 leading-relaxed">
          {useRecoveryCode
            ? 'Masukkan salah satu dari 8 digit kode pemulihan darurat Anda. Kode ini hanya dapat digunakan satu kali.'
            : 'Akun Anda dilindungi 2FA. Buka aplikasi Google Authenticator Anda dan masukkan 6 digit kode OTP.'}
        </p>

        {error && (
          <div className="bg-red-50 text-red-600 p-3 rounded-xl text-xs mb-4 text-left border border-red-200">
            {error}
          </div>
        )}

        {loading ? (
          <div className="py-8 text-center text-xs text-gray-400">
            <div className="w-6 h-6 border-2 border-[#1A3A5C] border-t-transparent rounded-full animate-spin mx-auto mb-2" />
            Memeriksa status akun...
          </div>
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            {useRecoveryCode ? (
              <div>
                <input
                  type="text"
                  required
                  maxLength={10}
                  placeholder="CONTOH: A1B2C3D4"
                  className="w-full text-center tracking-widest text-lg font-mono uppercase border border-gray-300 rounded-xl p-3 focus:outline-none focus:ring-2 focus:ring-[#1A3A5C] bg-gray-50"
                  value={recoveryCode}
                  onChange={(e) => setRecoveryCode(e.target.value)}
                  autoComplete="off"
                />
              </div>
            ) : (
              <div>
                <input
                  type="text"
                  required
                  maxLength={6}
                  placeholder="000000"
                  className="w-full text-center tracking-[0.5em] text-2xl font-mono border border-gray-300 rounded-xl p-3 focus:outline-none focus:ring-2 focus:ring-[#1A3A5C] bg-gray-50"
                  value={otp}
                  onChange={(e) => setOtp(e.target.value.replace(/\D/g, ''))}
                  autoComplete="one-time-code"
                  autoFocus
                />
              </div>
            )}

            <button
              type="submit"
              disabled={verifying || (useRecoveryCode ? !recoveryCode.trim() : otp.length < 6)}
              className="w-full bg-[#1A3A5C] text-white rounded-xl py-2.5 font-bold text-sm shadow-md hover:bg-[#152e4a] transition disabled:opacity-50 cursor-pointer"
            >
              {verifying ? 'Memverifikasi...' : 'Verifikasi & Masuk'}
            </button>
          </form>
        )}

        <div className="mt-5 pt-4 border-t border-gray-100 flex flex-col gap-2">
          <button
            type="button"
            onClick={() => {
              setUseRecoveryCode(!useRecoveryCode);
              setError(null);
            }}
            className="text-xs text-[#1A3A5C] font-semibold hover:underline cursor-pointer"
          >
            {useRecoveryCode
              ? '← Masukkan Kode OTP dari Authenticator'
              : 'Kehilangan akses aplikasi? Gunakan Kode Pemulihan'}
          </button>

          <button
            type="button"
            onClick={handleCancel}
            className="text-xs text-gray-400 hover:text-gray-600 cursor-pointer"
          >
            Batal & Keluar
          </button>
        </div>
      </div>
    </div>
  );
}
