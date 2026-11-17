import { useState } from 'react';
import { NavLink, Link } from 'react-router-dom';
import { useAuth } from '../../contexts/AuthContext';
import { ROLE_LABELS } from '../../types/roles';
import RoleSwitcher from '../layout/RoleSwitcher';
import NotificationBell from '../notification/NotificationBell';

/**
 * DashboardLayout — Layout shell terintegrasi & reusable untuk seluruh role.
 * Menyediakan sidebar responsif, header dengan role switcher, serta verifikasi role aktif.
 */
export default function DashboardLayout({
  requiredRole,
  title,
  subtitle,
  badgeIcon = '📊',
  navItems = [],
  children,
}) {
  const { user, roles, activeRole, changeActiveRole } = useAuth();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  const hasRequiredRole = roles && roles.includes(requiredRole);
  const isCurrentlyActive = activeRole === requiredRole;

  return (
    <div className="min-h-screen bg-neutral flex flex-col">
      {/* Top Warning Banner jika role aktif saat ini berbeda dengan dashboard yang dibuka */}
      {!isCurrentlyActive && (
        <div className="bg-amber-500 text-white px-4 py-2 text-xs font-semibold flex items-center justify-between sticky top-0 z-40 shadow-xs">
          <div className="flex items-center gap-2">
            <span>⚠️</span>
            <span>
              Anda sedang aktif sebagai <strong>{ROLE_LABELS[activeRole] || activeRole}</strong>, tetapi halaman ini adalah dashboard <strong>{ROLE_LABELS[requiredRole] || requiredRole}</strong>.
            </span>
          </div>
          {hasRequiredRole ? (
            <button
              onClick={() => changeActiveRole(requiredRole)}
              className="bg-white text-amber-900 px-3 py-1 rounded-md text-xs font-bold hover:bg-white/90 transition-colors cursor-pointer shadow-xs"
            >
              Beralih ke {ROLE_LABELS[requiredRole] || requiredRole}
            </button>
          ) : (
            <span className="text-white/80 italic">Akun Anda tidak memiliki role ini</span>
          )}
        </div>
      )}

      {/* Main Container */}
      <div className="flex-1 flex">
        {/* Sidebar Desktop */}
        <aside className="hidden md:flex flex-col w-64 bg-primary text-white shrink-0 border-r border-primary-dark">
          <div className="p-5 border-b border-white/10">
            <Link to="/" className="flex items-center gap-2.5 font-bold text-lg">
              <span className="text-2xl">{badgeIcon}</span>
              <div>
                <h2 className="leading-tight text-white font-extrabold text-base">Pusat Laundry</h2>
                <p className="text-[11px] text-white/60 font-normal">{ROLE_LABELS[requiredRole] || requiredRole}</p>
              </div>
            </Link>
          </div>

          {/* Navigation Links */}
          <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
            {navItems.map((item, idx) => (
              <NavLink
                key={idx}
                to={item.path}
                end={item.end}
                className={({ isActive }) =>
                  `flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all ${
                    isActive
                      ? 'bg-secondary text-white shadow-xs font-bold'
                      : 'text-white/70 hover:bg-white/10 hover:text-white'
                  }`
                }
              >
                <span className="text-base">{item.icon}</span>
                <span>{item.label}</span>
              </NavLink>
            ))}
          </nav>

          {/* Sidebar Footer User Info */}
          <div className="p-4 border-t border-white/10 bg-primary-dark/40 flex items-center justify-between">
            <div className="flex items-center gap-2.5 overflow-hidden">
              {user?.avatar ? (
                <img src={user.avatar} alt="Avatar" className="w-8 h-8 rounded-full border border-white/20 shrink-0" />
              ) : (
                <div className="w-8 h-8 rounded-full bg-secondary text-white font-bold text-xs flex items-center justify-center shrink-0">
                  {user?.name?.charAt(0) || 'U'}
                </div>
              )}
              <div className="truncate">
                <p className="text-xs font-bold text-white truncate">{user?.name}</p>
                <p className="text-[10px] text-white/60 truncate">{user?.email}</p>
              </div>
            </div>
          </div>
        </aside>

        {/* Mobile Sidebar Modal Drawer */}
        {mobileMenuOpen && (
          <div className="fixed inset-0 z-50 flex md:hidden">
            <div
              className="fixed inset-0 bg-black/50 backdrop-blur-xs"
              onClick={() => setMobileMenuOpen(false)}
            />
            <aside className="relative w-72 bg-primary text-white flex flex-col z-10">
              <div className="p-5 border-b border-white/10 flex items-center justify-between">
                <div className="flex items-center gap-2 font-bold">
                  <span className="text-2xl">{badgeIcon}</span>
                  <div>
                    <h2 className="leading-tight text-white font-extrabold text-base">Pusat Laundry</h2>
                    <p className="text-[11px] text-white/60 font-normal">{ROLE_LABELS[requiredRole] || requiredRole}</p>
                  </div>
                </div>
                <button
                  onClick={() => setMobileMenuOpen(false)}
                  className="text-white/70 hover:text-white p-1 text-lg"
                >
                  ✕
                </button>
              </div>

              <nav className="flex-1 p-3 space-y-1 overflow-y-auto">
                {navItems.map((item, idx) => (
                  <NavLink
                    key={idx}
                    to={item.path}
                    end={item.end}
                    onClick={() => setMobileMenuOpen(false)}
                    className={({ isActive }) =>
                      `flex items-center gap-3 px-3.5 py-2.5 rounded-xl text-xs font-semibold transition-all ${
                        isActive
                          ? 'bg-secondary text-white shadow-xs font-bold'
                          : 'text-white/70 hover:bg-white/10 hover:text-white'
                      }`
                    }
                  >
                    <span className="text-base">{item.icon}</span>
                    <span>{item.label}</span>
                  </NavLink>
                ))}
              </nav>

              <div className="p-4 border-t border-white/10">
                <RoleSwitcher />
              </div>
            </aside>
          </div>
        )}

        {/* Content Area */}
        <div className="flex-1 flex flex-col min-w-0">
          {/* Header Bar */}
          <header className="bg-white border-b border-gray-200 px-4 sm:px-6 py-3.5 sticky top-0 z-20 shadow-xs flex items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              <button
                onClick={() => setMobileMenuOpen(true)}
                className="md:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100 cursor-pointer"
                aria-label="Buka Menu"
              >
                ☰
              </button>
              <div>
                <h1 className="text-lg font-bold text-gray-900 leading-tight">{title}</h1>
                {subtitle && <p className="text-xs text-gray-500 hidden sm:block">{subtitle}</p>}
              </div>
            </div>

            <div className="flex items-center gap-3">
              {/* Notifikasi Bell */}
              <NotificationBell />

              {/* Role Switcher Pill */}
              <div className="bg-neutral px-2 py-1 rounded-lg border border-gray-200">
                <RoleSwitcher />
              </div>

              <Link
                to="/"
                className="text-xs text-gray-600 hover:text-primary font-medium px-2.5 py-1.5 rounded-lg hover:bg-gray-100 transition-colors hidden sm:inline-block"
              >
                🏠 Beranda
              </Link>
            </div>
          </header>

          {/* Main Body */}
          <main className="flex-1 p-4 sm:p-6 lg:p-8 max-w-7xl w-full mx-auto space-y-6">
            {children}
          </main>
        </div>
      </div>
    </div>
  );
}
