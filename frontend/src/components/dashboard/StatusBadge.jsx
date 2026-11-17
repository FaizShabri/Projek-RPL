/**
 * StatusBadge — Komponen badge warna standar untuk berbagai status (Toko, Order, User, Dispute).
 */
export default function StatusBadge({ status, type = 'general' }) {
  const normalized = (status || '').toLowerCase();

  const configs = {
    // Toko Statuses
    approved: { label: 'Approved', style: 'bg-emerald-100 text-emerald-800 border-emerald-200' },
    submitted: { label: 'Menunggu Review', style: 'bg-amber-100 text-amber-800 border-amber-200' },
    rejected: { label: 'Ditolak', style: 'bg-rose-100 text-rose-800 border-rose-200' },
    suspended: { label: 'Suspended', style: 'bg-gray-200 text-gray-800 border-gray-300' },

    // Dispute Statuses
    open: { label: 'Open (Baru)', style: 'bg-rose-100 text-rose-800 border-rose-200' },
    in_review: { label: 'Dalam Review', style: 'bg-blue-100 text-blue-800 border-blue-200' },
    under_review: { label: 'Dalam Review', style: 'bg-blue-100 text-blue-800 border-blue-200' },
    resolved: { label: 'Selesai (Resolved)', style: 'bg-emerald-100 text-emerald-800 border-emerald-200' },
    rejected: { label: 'Ditolak (Rejected)', style: 'bg-rose-100 text-rose-800 border-rose-200' },

    // User Statuses
    active: { label: 'Aktif', style: 'bg-emerald-100 text-emerald-800 border-emerald-200' },

    // Settlement Statuses
    ready: { label: 'Siap Cair', style: 'bg-emerald-100 text-emerald-800 border-emerald-200' },
    below_threshold: { label: '< Min. Cair', style: 'bg-amber-100 text-amber-800 border-amber-200' },
    settled: { label: 'Lunas', style: 'bg-gray-100 text-gray-700 border-gray-200' },

    // Order Statuses
    menunggu_konfirmasi: { label: 'Menunggu Konfirmasi', style: 'bg-amber-100 text-amber-800 border-amber-200' },
    menunggu_kurir: { label: 'Menunggu Kurir', style: 'bg-blue-100 text-blue-800 border-blue-200' },
    dijemput: { label: 'Dijemput Kurir', style: 'bg-indigo-100 text-indigo-800 border-indigo-200' },
    di_toko: { label: 'Tiba di Toko', style: 'bg-purple-100 text-purple-800 border-purple-200' },
    menunggu_pembayaran_info: { label: 'Ditimbang', style: 'bg-teal-100 text-teal-800 border-teal-200' },
    diproses: { label: 'Sedang Dicuci', style: 'bg-sky-100 text-sky-800 border-sky-200' },
    siap_diantar: { label: 'Siap Diantar', style: 'bg-cyan-100 text-cyan-800 border-cyan-200' },
    diantar: { label: 'Sedang Diantar', style: 'bg-yellow-100 text-yellow-800 border-yellow-200' },
    cod_selesai: { label: 'COD Selesai', style: 'bg-emerald-100 text-emerald-800 border-emerald-200' },
    selesai: { label: 'Selesai', style: 'bg-emerald-100 text-emerald-800 border-emerald-200' },
    dibatalkan: { label: 'Dibatalkan', style: 'bg-rose-100 text-rose-800 border-rose-200' },
  };

  const item = configs[normalized] || {
    label: status || 'Unknown',
    style: 'bg-gray-100 text-gray-700 border-gray-200',
  };

  return (
    <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold border ${item.style}`}>
      {item.label}
    </span>
  );
}
