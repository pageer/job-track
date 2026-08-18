import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { useAuth } from '../auth';
import { api, ApiError } from '../api';
import type { User } from '../types';
import { formatDate } from '../utils';
import ErrorBanner from '../components/ErrorBanner';
import Modal from '../components/Modal';

interface CreateFormState {
  name: string;
  email: string;
  password: string;
}

const emptyForm: CreateFormState = { name: '', email: '', password: '' };

export default function UsersPage() {
  const { user: currentUser } = useAuth();
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState<CreateFormState>(emptyForm);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.get<User[]>('/api/users');
      setUsers(data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to load users.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleCreate(e: FormEvent) {
    e.preventDefault();
    setSaving(true);
    setError(null);
    try {
      await api.post<{ user: User }>('/api/users', {
        name: form.name.trim(),
        email: form.email.trim(),
        password: form.password,
      });
      setShowModal(false);
      setForm(emptyForm);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to create the user.');
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(user: User) {
    if (!window.confirm(`Delete user "${user.name}" (${user.email})? This removes all their data.`)) {
      return;
    }
    setError(null);
    try {
      await api.delete(`/api/users/${user.id}`);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to delete the user.');
    }
  }

  const isAdmin = (roles: string[]) => roles.includes('ROLE_ADMIN');

  return (
    <div className="page">
      <div className="page-header">
        <h1>Users</h1>
        <button type="button" className="btn btn-primary" onClick={() => setShowModal(true)}>
          New user
        </button>
      </div>
      <ErrorBanner message={error} />

      {loading ? (
        <div className="spinner" aria-label="Loading" />
      ) : (
        <table className="table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Email</th>
              <th>Roles</th>
              <th>Created</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {users.map((u) => (
              <tr key={u.id}>
                <td>
                  {u.name}
                  {currentUser?.id === u.id && <span className="badge">you</span>}
                </td>
                <td>{u.email}</td>
                <td>{isAdmin(u.roles) ? 'Admin' : 'User'}</td>
                <td>{formatDate(u.createdAt)}</td>
                <td className="cell-actions">
                  {currentUser?.id !== u.id && (
                    <button type="button" className="btn btn-sm btn-danger-ghost" onClick={() => void handleDelete(u)}>
                      Delete
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      )}

      {showModal && (
        <Modal title="New user" onClose={() => setShowModal(false)}>
          <form onSubmit={handleCreate} className="form">
            <label className="field">
              <span>Name</span>
              <input
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                required
                autoFocus
              />
            </label>
            <label className="field">
              <span>Email</span>
              <input
                type="email"
                value={form.email}
                onChange={(e) => setForm({ ...form, email: e.target.value })}
                required
              />
            </label>
            <label className="field">
              <span>Password</span>
              <input
                type="password"
                value={form.password}
                onChange={(e) => setForm({ ...form, password: e.target.value })}
                required
                minLength={8}
              />
              <small>At least 8 characters. New users get a "Job Search" automatically.</small>
            </label>
            <div className="form-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowModal(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Creating…' : 'Create user'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
