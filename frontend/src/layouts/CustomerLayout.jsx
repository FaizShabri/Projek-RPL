import { Outlet, NavLink } from 'react-router-dom';

export default function CustomerLayout() {
  return (
    <div className="min-h-screen bg-neutral">
      <div className="bg-white border-b border-gray-200 px-4 py-2 sticky top-0 z-30 shadow-xs">
        <div className="max-w-6xl mx-auto flex items-center justify-between">
          <div className="flex items-center gap-1 sm:gap-3 text-xs font-bold">
            <NavLink
              to="/customer"
              end
              className={({ isActive }) =>
                `px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 ${
                  isActive ? 'bg-primary text-white shadow-xs' : 'text-gray-600 hover:text-primary hover:bg-gray-100'
                }`
              }
            >
              <span>🧺</span> Katalog Toko
            </NavLink>
            <NavLink
              to="/customer/orders"
              className={({ isActive }) =>
                `px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 ${
                  isActive ? 'bg-primary text-white shadow-xs' : 'text-gray-600 hover:text-primary hover:bg-gray-100'
                }`
              }
            >
              <span>📦</span> Pesanan Saya
            </NavLink>
            <NavLink
              to="/customer/reviews"
              className={({ isActive }) =>
                `px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5 ${
                  isActive ? 'bg-primary text-white shadow-xs' : 'text-gray-600 hover:text-primary hover:bg-gray-100'
                }`
              }
            >
              <span>⭐</span> Ulasan Saya
            </NavLink>
          </div>
        </div>
      </div>
      <Outlet />
    </div>
  );
}
