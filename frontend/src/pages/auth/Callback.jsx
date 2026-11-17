import { useEffect } from 'react';
import { useNavigate, useSearchParams } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';

export default function AuthCallback() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const { login } = useAuth();

  useEffect(() => {
    const token = searchParams.get('token');
    
    if (token) {
      login(token);
      // Pindahkan user ke halaman utama setelah login sukses.
      // Jika butuh 2FA, API /auth/me akan intercept & lempar ke /2fa/challenge.
      navigate('/');
    } else {
      navigate('/login?error=no_token');
    }
  }, [searchParams, navigate, login]);

  return (
    <div className="min-h-screen bg-neutral flex items-center justify-center">
      <div className="flex flex-col items-center gap-3">
        <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin" />
        <p className="text-primary font-medium">Memproses otentikasi...</p>
      </div>
    </div>
  );
}
