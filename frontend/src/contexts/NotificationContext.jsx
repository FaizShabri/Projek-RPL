import { createContext, useContext, useState, useEffect, useCallback, useRef } from 'react';
import { api } from '../api/client';
import { useAuth } from './AuthContext';

const NotificationContext = createContext(null);

export function NotificationProvider({ children }) {
  const { isAuthenticated } = useAuth();
  const [unreadCount, setUnreadCount] = useState(0);
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(false);
  const [latestToast, setLatestToast] = useState(null);
  const prevCountRef = useRef(0);

  const fetchUnreadCount = useCallback(async () => {
    if (!isAuthenticated) return;
    try {
      const res = await api.get('/notifications/unread-count');
      const count = res.unread_count ?? 0;

      // Jika unread count bertambah dan bukan initial load, ambil notifikasi terbaru untuk toast
      if (count > prevCountRef.current && prevCountRef.current !== 0) {
        const notifRes = await api.get('/notifications?per_page=1');
        const latest = notifRes.notifications?.data?.[0];
        if (latest && !latest.read_at) {
          setLatestToast({
            id: latest.id,
            title: latest.data?.title || 'Pemberitahuan Baru',
            message: latest.data?.message || 'Ada pembaruan status pesanan.',
            actionUrl: latest.data?.action_url || '/customer/orders',
          });
        }
      }

      prevCountRef.current = count;
      setUnreadCount(count);
    } catch (err) {
      // Diamkan error polling agar tidak mengganggu UI utama
    }
  }, [isAuthenticated]);

  const fetchNotifications = useCallback(async (perPage = 15) => {
    if (!isAuthenticated) return;
    setLoading(true);
    try {
      const res = await api.get(`/notifications?per_page=${perPage}`);
      const list = res.notifications?.data ?? [];
      setNotifications(list);
    } catch (err) {
      console.error('Failed to fetch notifications:', err);
    } finally {
      setLoading(false);
    }
  }, [isAuthenticated]);

  const markAsRead = async (id, actionUrl = null) => {
    try {
      await api.patch(`/notifications/${id}/read`, {});
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, read_at: new Date().toISOString() } : n))
      );
      setUnreadCount((c) => Math.max(0, c - 1));
      prevCountRef.current = Math.max(0, prevCountRef.current - 1);
    } catch (err) {
      console.error('Failed to mark notification as read:', err);
    }
  };

  const markAllAsRead = async () => {
    try {
      await api.post('/notifications/mark-all-read', {});
      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read_at: new Date().toISOString() }))
      );
      setUnreadCount(0);
      prevCountRef.current = 0;
    } catch (err) {
      console.error('Failed to mark all as read:', err);
    }
  };

  const dismissToast = () => setLatestToast(null);

  // Polling unread count setiap 30 detik saat user terotentikasi
  useEffect(() => {
    if (!isAuthenticated) {
      setUnreadCount(0);
      setNotifications([]);
      prevCountRef.current = 0;
      return;
    }

    fetchUnreadCount();
    const interval = setInterval(fetchUnreadCount, 30000);
    return () => clearInterval(interval);
  }, [isAuthenticated, fetchUnreadCount]);

  return (
    <NotificationContext.Provider
      value={{
        unreadCount,
        notifications,
        loading,
        latestToast,
        fetchNotifications,
        fetchUnreadCount,
        markAsRead,
        markAllAsRead,
        dismissToast,
      }}
    >
      {children}
    </NotificationContext.Provider>
  );
}

export function useNotifications() {
  const context = useContext(NotificationContext);
  if (!context) {
    throw new Error('useNotifications must be used within a NotificationProvider');
  }
  return context;
}
