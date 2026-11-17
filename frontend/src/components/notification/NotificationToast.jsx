import { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useNotifications } from '../../contexts/NotificationContext';

export default function NotificationToast() {
  const { latestToast, dismissToast } = useNotifications();
  const navigate = useNavigate();

  useEffect(() => {
    if (!latestToast) return;
    const timer = setTimeout(() => {
      dismissToast();
    }, 5000);
    return () => clearTimeout(timer);
  }, [latestToast, dismissToast]);

  if (!latestToast) return null;

  const handleClick = () => {
    if (latestToast.actionUrl) {
      navigate(latestToast.actionUrl);
    }
    dismissToast();
  };

  return (
    <div className="fixed top-4 right-4 z-50 max-w-sm w-full bg-white border border-primary/20 rounded-xl shadow-xl p-4 animate-bounce-in transition-all">
      <div className="flex items-start gap-3">
        <div className="w-9 h-9 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0 text-lg">
          🔔
        </div>
        <div className="flex-1 cursor-pointer" onClick={handleClick}>
          <h4 className="text-sm font-bold text-gray-900 leading-tight">
            {latestToast.title}
          </h4>
          <p className="text-xs text-gray-600 mt-1 line-clamp-2">
            {latestToast.message}
          </p>
          <span className="inline-block mt-2 text-[11px] font-semibold text-primary hover:underline">
            Lihat Detail Pesanan &rarr;
          </span>
        </div>
        <button
          onClick={(e) => {
            e.stopPropagation();
            dismissToast();
          }}
          className="text-gray-400 hover:text-gray-600 text-sm font-bold cursor-pointer p-1"
          aria-label="Tutup"
        >
          ✕
        </button>
      </div>
    </div>
  );
}
