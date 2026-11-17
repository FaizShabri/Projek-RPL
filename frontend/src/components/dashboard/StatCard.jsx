/**
 * StatCard — Komponen visualisasi statistik reusable untuk seluruh dashboard.
 */
export default function StatCard({
  title,
  value,
  icon,
  subtitle,
  variant = 'primary', // 'primary' | 'secondary' | 'tertiary' | 'amber' | 'purple' | 'rose'
}) {
  const variantStyles = {
    primary: {
      bg: 'bg-primary/5',
      text: 'text-primary',
      border: 'border-primary/20',
      iconBg: 'bg-primary/10 text-primary',
    },
    secondary: {
      bg: 'bg-secondary/5',
      text: 'text-secondary',
      border: 'border-secondary/20',
      iconBg: 'bg-secondary/10 text-secondary',
    },
    tertiary: {
      bg: 'bg-tertiary/10',
      text: 'text-tertiary-dark',
      border: 'border-tertiary/30',
      iconBg: 'bg-tertiary/20 text-tertiary-dark',
    },
    amber: {
      bg: 'bg-amber-50',
      text: 'text-amber-800',
      border: 'border-amber-200',
      iconBg: 'bg-amber-100 text-amber-700',
    },
    purple: {
      bg: 'bg-purple-50',
      text: 'text-purple-800',
      border: 'border-purple-200',
      iconBg: 'bg-purple-100 text-purple-700',
    },
    rose: {
      bg: 'bg-rose-50',
      text: 'text-rose-800',
      border: 'border-rose-200',
      iconBg: 'bg-rose-100 text-rose-700',
    },
  };

  const style = variantStyles[variant] || variantStyles.primary;

  return (
    <div className={`p-4 sm:p-5 rounded-2xl border ${style.border} bg-white shadow-xs transition-all hover:shadow-sm`}>
      <div className="flex items-center justify-between gap-3">
        <div className="space-y-1">
          <p className="text-xs font-semibold text-gray-500 uppercase tracking-wider">{title}</p>
          <p className={`text-xl sm:text-2xl font-black font-mono ${style.text}`}>{value}</p>
        </div>
        {icon && (
          <div className={`w-12 h-12 rounded-xl flex items-center justify-center text-xl shrink-0 ${style.iconBg}`}>
            {icon}
          </div>
        )}
      </div>
      {subtitle && (
        <p className="text-[11px] text-gray-400 mt-2.5 pt-2 border-t border-gray-100">
          {subtitle}
        </p>
      )}
    </div>
  );
}
