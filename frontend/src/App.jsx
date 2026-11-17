import { useEffect } from 'react';
import { BrowserRouter, Routes, Route, Navigate, Link, useNavigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './contexts/AuthContext';
import Home from './pages/Home';
import RoleSwitcher from './components/layout/RoleSwitcher';
import ChatWidget from './components/chatbot/ChatWidget';

// Auth Pages
import Login from './pages/auth/Login';
import Register from './pages/auth/Register';
import ForgotPassword from './pages/auth/ForgotPassword';
import ResetPassword from './pages/auth/ResetPassword';
import AcceptInvitation from './pages/auth/AcceptInvitation';
import AuthCallback from './pages/auth/Callback';
import Onboarding from './pages/auth/Onboarding';
import TwoFactorChallenge from './pages/auth/TwoFactorChallenge';
import TwoFactorSetup from './pages/auth/TwoFactorSetup';
import VerifyEmailBanner from './components/common/VerifyEmailBanner';

// Owner Toko Pages
import TokoDashboard from './pages/owner-toko/TokoDashboard';
import OnboardingToko from './pages/owner-toko/OnboardingToko';

// Role Dashboard Pages
import PlatformOwnerDashboard from './pages/platform-owner/PlatformOwnerDashboard';
import AdminOwnerDashboard from './pages/admin-owner/AdminOwnerDashboard';
import AdminTokoDashboard from './pages/admin-toko/AdminTokoDashboard';
import TokoApprovalDashboard from './pages/admin-owner/TokoApprovalDashboard';

// Customer Marketplace Pages
import Marketplace from './pages/customer/Marketplace';
import TokoDetail from './pages/customer/TokoDetail';
import CustomerOrders from './pages/customer/CustomerOrders';
import CustomerReviews from './pages/customer/CustomerReviews';

// Kurir Pages
import KurirDashboard from './pages/kurir/KurirDashboard';

// Notification & Settings
import { NotificationProvider } from './contexts/NotificationContext';
import NotificationBell from './components/notification/NotificationBell';
import NotificationToast from './components/notification/NotificationToast';
import NotificationPreferences from './pages/settings/NotificationPreferences';
import AccountManagement from './pages/management/AccountManagement';

// Layout imports (placeholders)
import PlatformOwnerLayout from './layouts/PlatformOwnerLayout';
import AdminOwnerLayout from './layouts/AdminOwnerLayout';
import AdminTokoLayout from './layouts/AdminTokoLayout';
import OwnerTokoLayout from './layouts/OwnerTokoLayout';
import KurirLayout from './layouts/KurirLayout';
import CustomerLayout from './layouts/CustomerLayout';

/** Navbar global — tampil ketika user sudah login */
function Navbar() {
  const { user, isAuthenticated, logout } = useAuth();

  if (!isAuthenticated) return null;

  return (
    <nav className="bg-primary text-white px-4 py-3 flex items-center justify-between shadow-sm">
      <Link to="/" className="font-bold text-lg flex items-center gap-2">
        <span>🧺</span> Pusat Laundry
      </Link>
      <div className="flex items-center gap-4">
        <NotificationBell />
        <RoleSwitcher />
        <span className="text-sm hidden sm:inline">{user?.name}</span>
        {user?.avatar && (
          <img src={user.avatar} alt="avatar" className="w-8 h-8 rounded-full border-2 border-white/30" />
        )}
        <button onClick={logout} className="text-sm bg-white/10 px-3 py-1.5 rounded-lg hover:bg-white/20 transition-colors">
          Logout
        </button>
      </div>
    </nav>
  );
}

/** Wrapper route: redirect ke /login jika belum autentikasi */
function ProtectedRoute({ children }) {
  const { isAuthenticated, loading } = useAuth();

  if (loading) {
    return (
      <div className="min-h-screen bg-neutral flex items-center justify-center">
        <div className="w-8 h-8 border-4 border-primary border-t-transparent rounded-full animate-spin" />
      </div>
    );
  }

  return isAuthenticated ? children : <Navigate to="/login" />;
}

/** Wrapper route: redirect ke / jika sudah autentikasi */
function GuestRoute({ children }) {
  const { isAuthenticated, loading } = useAuth();

  if (loading) return null;

  return isAuthenticated ? <Navigate to="/" /> : children;
}

function AppRoutes() {
  const navigate = useNavigate();

  // Tangani navigasi 2FA dari API tanpa reload halaman dan anti-loop
  useEffect(() => {
    const handle2fa = (e) => {
      const destination = e.detail?.destination || '/2fa/challenge';
      navigate(destination);
    };

    window.addEventListener('auth:2fa_required', handle2fa);
    return () => window.removeEventListener('auth:2fa_required', handle2fa);
  }, [navigate]);

  return (
    <>
      <Navbar />
      <VerifyEmailBanner />
      <Routes>
        {/* Public / Guest routes */}
        <Route path="/login" element={<GuestRoute><Login /></GuestRoute>} />
        <Route path="/register" element={<GuestRoute><Register /></GuestRoute>} />
        <Route path="/forgot-password" element={<GuestRoute><ForgotPassword /></GuestRoute>} />
        <Route path="/reset-password" element={<ResetPassword />} />
        <Route path="/invitations/accept" element={<AcceptInvitation />} />
        <Route path="/invitation/accept" element={<AcceptInvitation />} />
        <Route path="/auth/callback" element={<AuthCallback />} />
        <Route path="/2fa/setup" element={<TwoFactorSetup />} />
        <Route path="/2fa/challenge" element={<TwoFactorChallenge />} />

        {/* Protected routes */}
        <Route path="/" element={<ProtectedRoute><Home /></ProtectedRoute>} />
        <Route path="/onboarding" element={<ProtectedRoute><Onboarding /></ProtectedRoute>} />

        {/* Platform Owner routes */}
        <Route path="/platform-owner" element={<ProtectedRoute><PlatformOwnerLayout /></ProtectedRoute>}>
          <Route index element={<PlatformOwnerDashboard />} />
        </Route>

        {/* Admin Owner routes */}
        <Route path="/admin-owner" element={<ProtectedRoute><AdminOwnerLayout /></ProtectedRoute>}>
          <Route index element={<AdminOwnerDashboard />} />
        </Route>

        {/* Admin Toko routes */}
        <Route path="/admin-toko" element={<ProtectedRoute><AdminTokoLayout /></ProtectedRoute>}>
          <Route index element={<AdminTokoDashboard />} />
        </Route>

        {/* Owner Toko routes */}
        <Route path="/owner-toko" element={<ProtectedRoute><OwnerTokoLayout /></ProtectedRoute>}>
          <Route index element={<TokoDashboard />} />
          <Route path="register" element={<OnboardingToko />} />
        </Route>

        {/* Kurir routes */}
        <Route path="/kurir" element={<ProtectedRoute><KurirLayout /></ProtectedRoute>}>
          <Route index element={<KurirDashboard />} />
        </Route>

        {/* Customer Marketplace & Orders routes */}
        <Route path="/customer" element={<ProtectedRoute><CustomerLayout /></ProtectedRoute>}>
          <Route index element={<Marketplace />} />
          <Route path="orders" element={<CustomerOrders />} />
          <Route path="reviews" element={<CustomerReviews />} />
          <Route path="tokos/:id" element={<TokoDetail />} />
        </Route>

        {/* Public / Quick Toko Detail route */}
        <Route path="/tokos/:id" element={<ProtectedRoute><TokoDetail /></ProtectedRoute>} />

        {/* Pengaturan Preferensi Notifikasi */}
        <Route path="/notification-preferences" element={<ProtectedRoute><NotificationPreferences /></ProtectedRoute>} />

        {/* Manajemen Akun & Hak Akses (Platform Owner, Admin Owner, Owner Toko) */}
        <Route path="/management/users" element={<ProtectedRoute><AccountManagement /></ProtectedRoute>} />
      </Routes>
    </>
  );
}

/** Temporary placeholder for role-specific dashboard pages */
function PlaceholderPage({ role }) {
  return (
    <div className="text-center py-12">
      <h2 className="text-2xl font-bold text-primary">Dashboard {role}</h2>
      <p className="text-gray-500 mt-2">Halaman ini akan dibangun di fase berikutnya.</p>
    </div>
  );
}

function App() {
  return (
    <AuthProvider>
      <NotificationProvider>
        <BrowserRouter>
          <AppRoutes />
          <NotificationToast />
          <ChatWidget />
        </BrowserRouter>
      </NotificationProvider>
    </AuthProvider>
  );
}

export default App;
