import { useState, useEffect, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import api from '../../api/client';
import { useAuth } from '../../contexts/AuthContext';

export default function TwoFactorSetup() {
  const navigate = useNavigate();
  const { login, logout, changeActiveRole } = useAuth();

  const [setupData, setSetupData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [otp, setOtp] = useState('');
  const [verifying, setVerifying] = useState(false);
  const [error, setError] = useState(null);
  const [copiedCodes, setCopiedCodes] = useState(false);
  const [copiedSecret, setCopiedSecret] = useState(false);

  const hasFetchedRef = useRef(false);

  useEffect(() => {
    if (hasFetchedRef.current) return;
    hasFetchedRef.current = true;

    const fetchSetup = async () => {
      try {
        setLoading(true);
        setError(null);

        // Periksa status auth tahap-tengah terlebih dahulu
        const statusRes = await api.get('/auth/2fa/status');
        if (statusRes.is_confirmed) {
          // Jika sudah terkonfirmasi, langsung arahkan ke challenge
          navigate('/2fa/challenge', { replace: true });
          return;
        }

        // Ambil data QR code, secret, dan recovery codes
        const data = await api.get('/auth/2fa/setup');
        setSetupData(data);
      } catch (err) {
        setError(err.message || 'Gagal memuat data setup 2FA. Sesi Anda mungkin telah kedaluwarsa.');
      } finally {
        setLoading(false);
      }
    };

    fetchSetup();
  }, [navigate]);

  const handleVerify = async (e) => {
    e.preventDefault();
    if (otp.length < 6) return;

    try {
      setVerifying(true);
      setError(null);

      const res = await api.post('/auth/2fa/verify', { otp: otp.trim() });

      // Simpan token penuh dan set role aktif
      login(res.token, res.user, res.roles);
      if (res.active_role) {
        changeActiveRole(res.active_role);
      }

      const defaultPath = res.active_role === 'platform_owner' ? '/platform-owner' :
        res.active_role === 'admin_owner' ? '/admin-owner' : '/';

      navigate(defaultPath, { replace: true });
    } catch (err) {
      setError(err.message || 'Kode OTP tidak valid. Pastikan jam perangkat Anda akurat.');
    } finally {
      setVerifying(false);
    }
  };

  const handleCancel = () => {
    logout();
    navigate('/login', { replace: true });
  };

  const copyToClipboard = (text, type) => {
    navigator.clipboard.writeText(text);
    if (type === 'secret') {
      setCopiedSecret(true);
      setTimeout(() => setCopiedSecret(false), 2000);
    } else {
      setCopiedCodes(true);
      setTimeout(() => setCopiedCodes(false), 2000);
    }
  };

  return (
    <div className="min-h-screen bg-neutral flex items-center justify-center p-4 py-8">
      <div className="bg-white rounded-3xl shadow-xl max-w-lg w-full p-6 sm:p-8 border border-gray-100">
        <div className="text-center mb-6">
          <div className="inline-flex items-center justify-center w-14 h-14 bg-[#1A3A5C] text-white rounded-2xl mb-3 shadow-md">
            <span className="text-2xl">🔐</span>
          </div>
          <h1 className="text-2xl font-black text-[#1A3A5C] tracking-tight">Setup Otentikasi 2FA</h1>
          <p className="text-xs text-gray-500 mt-1">
            Akun administrator Anda diwajibkan mengaktifkan Verifikasi Dua Langkah (TOTP).
          </p>
        </div>

        {error && (
          <div className="mb-5 p-3.5 bg-red-50 border border-red-200 text-red-700 text-xs rounded-xl flex items-start gap-2">
            <span>⚠️</span>
            <div className="flex-1">
              <p className="font-semibold">{error}</p>
              <button
                type="button"
                onClick={handleCancel}
                className="mt-2 text-[11px] underline font-bold cursor-pointer text-red-800"
              >
                Kembali ke Login
              </button>
            </div>
          </div>
        )}

        {loading ? (
          <div className="py-12 text-center text-xs text-gray-400">
            <div className="w-8 h-8 border-4 border-[#1A3A5C] border-t-transparent rounded-full animate-spin mx-auto mb-3" />
            Menyiapkan kunci keamanan 2FA Anda...
          </div>
        ) : setupData ? (
          <div className="space-y-6">
            {/* Langkah 1: Pindai QR Code atau Masukkan Manual */}
            <div className="bg-slate-50 border border-slate-200 rounded-2xl p-4 text-xs space-y-3">
              <div className="font-bold text-gray-800 flex items-center gap-1.5 text-xs uppercase tracking-wider">
                <span className="w-5 h-5 rounded-full bg-[#1A3A5C] text-white inline-flex items-center justify-center text-[10px]">1</span>
                <span>Pindai QR Code di Google Authenticator</span>
              </div>
              <p className="text-gray-500 text-[11px]">
                Buka aplikasi <strong>Google Authenticator</strong> di HP Anda, pilih menu Scan QR Code, atau masukkan Secret Key secara manual.
              </p>

              <div className="flex flex-col sm:flex-row items-center justify-center gap-4 py-2">
                {setupData.qr_code_url && (
                  <div className="bg-white p-2.5 rounded-xl border border-gray-200 shadow-2xs">
                    <img
                      src={`https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=${encodeURIComponent(setupData.qr_code_url)}`}
                      alt="2FA QR Code"
                      className="w-36 h-36 mx-auto"
                    />
                  </div>
                )}

                <div className="flex-1 space-y-1.5 text-center sm:text-left">
                  <span className="text-[10px] text-gray-400 font-semibold block uppercase">Secret Key Manual:</span>
                  <div className="bg-white font-mono text-xs font-bold text-gray-800 p-2.5 rounded-xl border border-gray-200 break-all select-all tracking-wider">
                    {setupData.secret}
                  </div>
                  <button
                    type="button"
                    onClick={() => copyToClipboard(setupData.secret, 'secret')}
                    className="text-[11px] text-[#1A3A5C] font-semibold hover:underline cursor-pointer inline-flex items-center gap-1"
                  >
                    {copiedSecret ? '✓ Secret Disalin!' : '📋 Salin Secret Key'}
                  </button>
                </div>
              </div>
            </div>

            {/* Langkah 2: Simpan Kode Pemulihan (Recovery Codes) */}
            <div className="bg-amber-50/70 border border-amber-200 rounded-2xl p-4 text-xs space-y-2">
              <div className="flex items-center justify-between">
                <div className="font-bold text-amber-900 flex items-center gap-1.5 text-xs uppercase tracking-wider">
                  <span className="w-5 h-5 rounded-full bg-amber-700 text-white inline-flex items-center justify-center text-[10px]">2</span>
                  <span>Simpan 8 Kode Pemulihan Darurat</span>
                </div>
                <button
                  type="button"
                  onClick={() => copyToClipboard(setupData.recovery_codes.join('\n'), 'codes')}
                  className="text-[11px] text-amber-900 font-bold hover:underline cursor-pointer"
                >
                  {copiedCodes ? '✓ Tersalin!' : '📋 Salin Semua'}
                </button>
              </div>
              <p className="text-amber-800 text-[11px]">
                Gunakan kode ini bila kehilangan akses ponsel. Masing-masing hanya berlaku satu kali.
              </p>

              <div className="grid grid-cols-2 sm:grid-cols-4 gap-1.5 pt-1">
                {setupData.recovery_codes?.map((code, idx) => (
                  <span
                    key={idx}
                    className="bg-white border border-amber-200 font-mono text-[11px] font-bold text-center py-1 rounded-lg text-gray-800 tracking-wider shadow-3xs"
                  >
                    {code}
                  </span>
                ))}
              </div>
            </div>

            {/* Langkah 3: Verifikasi Kode Pertama */}
            <form onSubmit={handleVerify} className="space-y-4">
              <div className="font-bold text-gray-800 flex items-center gap-1.5 text-xs uppercase tracking-wider">
                <span className="w-5 h-5 rounded-full bg-[#1A3A5C] text-white inline-flex items-center justify-center text-[10px]">3</span>
                <span>Konfirmasi Kode OTP 6 Digit Pertama</span>
              </div>

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

              <button
                type="submit"
                disabled={verifying || otp.length < 6}
                className="w-full bg-[#1A3A5C] text-white rounded-xl py-3 font-bold text-sm shadow-md hover:bg-[#152e4a] transition disabled:opacity-50 cursor-pointer flex items-center justify-center gap-2"
              >
                {verifying ? (
                  <>
                    <span className="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" />
                    <span>Mengonfirmasi...</span>
                  </>
                ) : (
                  'Aktifkan 2FA & Lanjutkan ke Dashboard'
                )}
              </button>

              <div className="text-center pt-2">
                <button
                  type="button"
                  onClick={handleCancel}
                  className="text-xs text-gray-400 hover:text-gray-600 font-medium cursor-pointer"
                >
                  Batal & Keluar
                </button>
              </div>
            </form>
          </div>
        ) : null}
      </div>
    </div>
  );
}
