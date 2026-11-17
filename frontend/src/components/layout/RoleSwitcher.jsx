import { useAuth } from '../../contexts/AuthContext';
import { ROLE_LABELS } from '../../types/roles';

/**
 * RoleSwitcher — tampil di header jika user memiliki lebih dari 1 role.
 * Mengubah state `activeRole` di AuthContext, yang otomatis dipakai oleh apiClient
 * sebagai header X-Active-Role di setiap request.
 */
export default function RoleSwitcher() {
  const { roles, activeRole, changeActiveRole } = useAuth();

  // Jangan tampilkan jika user hanya punya 1 role
  if (!roles || roles.length <= 1) return null;

  return (
    <div className="flex items-center gap-2">
      <label className="text-sm text-white/80 hidden sm:inline">Aktif sebagai:</label>
      <select
        value={activeRole || ''}
        onChange={(e) => changeActiveRole(e.target.value)}
        className="bg-primary-light text-white text-sm border border-white/20 rounded-md px-2 py-1 cursor-pointer focus:outline-none focus:ring-2 focus:ring-secondary"
      >
        {roles.map((role) => (
          <option key={role} value={role} className="bg-primary text-white">
            {ROLE_LABELS[role] || role}
          </option>
        ))}
      </select>
    </div>
  );
}
