import { useState, useRef, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useNotifications } from '../../contexts/NotificationContext';

export default function NotificationBell() {
  const { unreadCount, notifications, loading, fetchNotifications, markAsRead, markAllAsRead } = useNotifications();
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef(null);
  const navigate = useNavigate();

  // Buka dropdown & ambil notifikasi terbaru
  const toggleDropdown = () => {
    if (!isOpen) {
      fetchNotifications();
    }
    setIsOpen(!isOpen);
  };

  // Close when clicking outside
  useEffect(() => {
    function handleClickOutside(event) {
      if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
        setIsOpen(false);
      }
    }
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  const handleNotificationClick = async (notif) => {
    if (!notif.read_at) {
      await markAsRead(notif.id);
    }
    setIsOpen(false);
    const targetUrl = notif.data?.action_url || '/customer/orders';
    navigate(targetUrl);
  };

  // Icon helper sesuai tipe notifikasi
  const getIcon = (type) => {
    switch (type) {
      case 'cod_fee':
        return '💰';
      case 'order_cancelled':
        return '❌';
      case 'new_task':
        return '🛵';
      case 'new_order':
        return '🧺';
      case 'new_review':
        return '⭐';
      case 'dispute':
        return '⚠️';
      case 'toko_registered':
        return '🏪';
      default:
        return '📋';
    }
  };

  return (
    <div className="relative inline-block text-left" ref={dropdownRef}>
      {/* Bell Button */}
      <button
        type="button"
        onClick={toggleDropdown}
        className="relative p-2 rounded-lg text-gray-600 hover:text-primary hover:bg-gray-100 transition-colors cursor-pointer focus:outline-hidden"
        aria-label="Notifikasi"
      >
        <span className="text-xl">🔔</span>

        {/* Unread Badge */}
        {unreadCount > 0 && (
          <span className="absolute top-1 right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-extrabold leading-none text-white bg-red-600 rounded-full min-w-[18px] h-[18px] shadow-xs">
            {unreadCount > 99 ? '99+' : unreadCount}
          </span>
        )}
      </button>

      {/* Dropdown Menu */}
      {isOpen && (
        <div className="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-xl shadow-2xl border border-gray-100 z-50 overflow-hidden animate-in fade-in zoom-in-95 duration-100">
          {/* Header Dropdown */}
          <div className="p-3.5 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
            <div className="flex items-center gap-2">
              <h3 className="font-bold text-sm text-gray-800">Notifikasi</h3>
              {unreadCount > 0 && (
                <span className="text-xs bg-red-100 text-red-700 font-semibold px-2 py-0.5 rounded-full">
                  {unreadCount} baru
                </span>
              )}
            </div>
            {unreadCount > 0 && (
              <button
                onClick={markAllAsRead}
                className="text-xs text-primary hover:underline font-medium cursor-pointer"
              >
                Tandai semua dibaca
              </button>
            )}
          </div>

          {/* List Notifikasi */}
          <div className="max-h-96 overflow-y-auto divide-y divide-gray-100">
            {loading ? (
              <div className="p-6 text-center text-xs text-gray-500">
                <div className="w-5 h-5 border-2 border-primary border-t-transparent rounded-full animate-spin mx-auto mb-2" />
                Memuat notifikasi...
              </div>
            ) : notifications.length === 0 ? (
              <div className="p-8 text-center">
                <span className="text-3xl block mb-2">📭</span>
                <p className="text-sm font-medium text-gray-600">Belum ada notifikasi</p>
                <p className="text-xs text-gray-400 mt-1">Pembaruan pesanan akan muncul di sini</p>
              </div>
            ) : (
              notifications.map((notif) => {
                const isUnread = !notif.read_at;
                const data = notif.data || {};
                const icon = getIcon(data.type);
                const timeAgo = notif.created_at ? new Date(notif.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '';

                return (
                  <div
                    key={notif.id}
                    onClick={() => handleNotificationClick(notif)}
                    className={`p-3.5 flex items-start gap-3 hover:bg-gray-50/80 transition-colors cursor-pointer ${
                      isUnread ? 'bg-blue-50/40' : 'bg-white'
                    }`}
                  >
                    <div className="text-xl shrink-0 mt-0.5">{icon}</div>
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between gap-1">
                        <p className={`text-xs font-semibold truncate ${isUnread ? 'text-gray-900 font-bold' : 'text-gray-700'}`}>
                          {data.title || 'Pemberitahuan'}
                        </p>
                        <span className="text-[10px] text-gray-400 shrink-0">{timeAgo}</span>
                      </div>
                      <p className="text-xs text-gray-600 mt-0.5 line-clamp-2">
                        {data.message}
                      </p>
                    </div>
                    {isUnread && (
                      <span className="w-2 h-2 rounded-full bg-primary shrink-0 mt-1.5" title="Belum dibaca" />
                    )}
                  </div>
                );
              })
            )}
          </div>

          {/* Footer Dropdown */}
          <div className="p-2.5 bg-gray-50 border-t border-gray-100 flex items-center justify-between text-xs">
            <Link
              to="/notification-preferences"
              onClick={() => setIsOpen(false)}
              className="text-gray-600 hover:text-primary font-medium flex items-center gap-1.5 py-1 px-2 rounded-md hover:bg-gray-100 transition-colors"
            >
              <span>⚙️</span> Pengaturan Preferensi
            </Link>
            <button
              onClick={() => {
                fetchNotifications();
              }}
              className="text-gray-500 hover:text-gray-800 py-1 px-2 rounded-md hover:bg-gray-100 transition-colors cursor-pointer"
            >
              🔄 Segarkan
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
