import { createContext, useContext, useState, useEffect } from 'react';
import api from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [roles, setRoles] = useState([]);
  const [token, setToken] = useState(localStorage.getItem('auth_token'));
  const [activeRole, setActiveRole] = useState(localStorage.getItem('active_role'));
  const [loading, setLoading] = useState(true);

  // Fetch /auth/me to get user info if token exists, KECUALI saat berada di halaman /2fa/*
  useEffect(() => {
    const is2faRoute = typeof window !== 'undefined' && window.location.pathname.startsWith('/2fa');

    if (token && !is2faRoute) {
      api.get('/auth/me')
        .then(data => {
          setUser(data.user);
          const roleList = Array.isArray(data.roles)
            ? data.roles.map(r => typeof r === 'string' ? r : r.name)
            : [];
          setRoles(roleList);
          // Set default active role if not set or invalid
          if (!activeRole || !roleList.includes(activeRole)) {
            const defaultRole = roleList.length > 0 ? roleList[0] : null;
            changeActiveRole(defaultRole);
          }
        })
        .catch(err => {
          // Jangan logout jika error adalah penolakan 2FA (token tahap-tengah)
          if (!err.requires_2fa && err.message !== '2FA Verification Required') {
            logout();
          }
        })
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, [token]);

  const login = (authToken, userData = null, userRoles = []) => {
    setToken(authToken);
    localStorage.setItem('auth_token', authToken);
    if (userData) {
      setUser(userData);
      const roleList = Array.isArray(userRoles) && userRoles.length > 0
        ? userRoles
        : (userData.roles ? userData.roles.map(r => typeof r === 'string' ? r : r.name) : []);
      setRoles(roleList);
      setLoading(false);
    }
  };

  const logout = () => {
    setUser(null);
    setRoles([]);
    setToken(null);
    setActiveRole(null);
    localStorage.removeItem('auth_token');
    localStorage.removeItem('active_role');
  };

  const changeActiveRole = (role) => {
    setActiveRole(role);
    if (role) {
      localStorage.setItem('active_role', role);
    } else {
      localStorage.removeItem('active_role');
    }
  };

  const reloadUser = async () => {
    if (token) {
      const data = await api.get('/auth/me');
      setUser(data.user);
      const roleList = Array.isArray(data.roles)
        ? data.roles.map(r => typeof r === 'string' ? r : r.name)
        : [];
      setRoles(roleList);
    }
  };

  const value = {
    user,
    roles,
    token,
    activeRole,
    isAuthenticated: !!token && !!user,
    loading,
    login,
    logout,
    changeActiveRole,
    reloadUser
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
}

export default AuthContext;
