import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link } from 'react-router-dom';
import { api, ApiError } from '../api';
import type { JobSearch } from '../types';
import { formatDate } from '../utils';
import ErrorBanner from '../components/ErrorBanner';
import Modal from '../components/Modal';

interface SearchFormState {
  name: string;
  startDate: string;
  endDate: string;
}

const emptyForm: SearchFormState = { name: '', startDate: '', endDate: '' };

export default function DashboardPage() {
  const [searches, setSearches] = useState<JobSearch[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState<SearchFormState>(emptyForm);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.get<JobSearch[]>('/api/job-searches');
      setSearches(data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to load job searches.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleCreate(e: FormEvent) {
    e.preventDefault();
    if (!form.name.trim() || !form.startDate) {
      return;
    }
    setSaving(true);
    setError(null);
    try {
      const body: Record<string, string> = {
        name: form.name.trim(),
        startDate: form.startDate,
      };
      if (form.endDate) {
        body.endDate = form.endDate;
      }
      await api.post<JobSearch>('/api/job-searches', body);
      setShowModal(false);
      setForm(emptyForm);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to create the job search.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Job Searches</h1>
        <button type="button" className="btn btn-primary" onClick={() => setShowModal(true)}>
          New job search
        </button>
      </div>
      <ErrorBanner message={error} />

      {loading ? (
        <div className="spinner" aria-label="Loading" />
      ) : searches.length === 0 ? (
        <div className="empty-state">
          <p>No job searches yet.</p>
          <button type="button" className="btn btn-primary" onClick={() => setShowModal(true)}>
            Create your first job search
          </button>
        </div>
      ) : (
        <ul className="card-list">
          {searches.map((s) => (
            <li key={s.id}>
              <Link to={`/searches/${s.id}`} className="card card-link">
                <div className="card-title-row">
                  <span className="card-title">{s.name}</span>
                  <span className="badge">{s.jobCount} jobs</span>
                </div>
                <div className="card-meta">
                  {formatDate(s.startDate)}
                  {s.endDate ? ` — ${formatDate(s.endDate)}` : ' — ongoing'}
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}

      {showModal && (
        <Modal title="New job search" onClose={() => setShowModal(false)}>
          <form onSubmit={handleCreate} className="form">
            <label className="field">
              <span>Name</span>
              <input
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                required
                autoFocus
                placeholder="e.g. Summer 2026"
              />
            </label>
            <label className="field">
              <span>Start date</span>
              <input
                type="date"
                value={form.startDate}
                onChange={(e) => setForm({ ...form, startDate: e.target.value })}
                required
              />
            </label>
            <label className="field">
              <span>End date (optional)</span>
              <input
                type="date"
                value={form.endDate}
                min={form.startDate || undefined}
                onChange={(e) => setForm({ ...form, endDate: e.target.value })}
              />
            </label>
            <div className="form-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowModal(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Creating…' : 'Create'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
