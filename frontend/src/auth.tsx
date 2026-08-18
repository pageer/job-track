import { createContext, useCallback, useContext, useEffect, useState, type ReactNode } from 'react';
import { api, getCsrfToken, setCsrfToken } from './api';
import type { User } from './types';

interface SetupStatus {
  needsSetup: boolean;
  csrfToken: string;
}

interface AuthResponse {
  user: User;
  csrfToken: string;
}

interface AuthContextValue {
  user: User | null;
  needsSetup: boolean | null;
  loading: boolean;
  refresh: () => Promise<void>;
  login: (email: string, password: string) => Promise<void>;
  setup: (name: string, email: string, password: string) => Promise<void>;
  logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [needsSetup, setNeedsSetup] = useState<boolean | null>(null);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    try {
      const setup = await api.get<SetupStatus>('/api/setup/status');
      setNeedsSetup(setup.needsSetup);
      if (setup.csrfToken) {
        setCsrfToken(setup.csrfToken);
      }
    } catch {
      // Ignore; we'll still try /me below.
    }

    try {
      const me = await api.get<AuthResponse>('/api/auth/me');
      setUser(me.user);
      if (me.csrfToken) {
        setCsrfToken(me.csrfToken);
      }
    } catch {
      setUser(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const login = useCallback(async (email: string, password: string) => {
    const resp = await api.post<AuthResponse>('/api/auth/login', { email, password });
    setCsrfToken(resp.csrfToken);
    setUser(resp.user);
    setNeedsSetup(false);
  }, []);

  const setup = useCallback(
    async (name: string, email: string, password: string) => {
      await api.post<{ user: User }>('/api/setup', { name, email, password });
      await login(email, password);
    },
    [login]
  );

  const logout = useCallback(async () => {
    try {
      await api.post('/api/auth/logout');
    } catch {
      // Even if logout fails server-side, clear local state.
    }
    setCsrfToken(null);
    setUser(null);
    await refresh();
  }, [refresh]);

  return (
    <AuthContext.Provider value={{ user, needsSetup, loading, refresh, login, setup, logout }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return ctx;
}

export function useCsrfToken(): string | null {
  return getCsrfToken();
}
