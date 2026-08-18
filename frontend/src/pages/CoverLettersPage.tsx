import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { api, ApiError } from '../api';
import type { CoverLetter } from '../types';
import { formatDate } from '../utils';
import ErrorBanner from '../components/ErrorBanner';
import Modal from '../components/Modal';

export default function CoverLettersPage() {
  const [letters, setLetters] = useState<CoverLetter[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<CoverLetter | null>(null);
  const [name, setName] = useState('');
  const [body, setBody] = useState('');
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.get<CoverLetter[]>('/api/cover-letters');
      setLetters(data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to load cover letters.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  function openModal(letter: CoverLetter | null) {
    setEditing(letter);
    setName(letter?.name ?? '');
    setBody(letter?.body ?? '');
    setShowModal(true);
  }

  async function handleSave(e: FormEvent) {
    e.preventDefault();
    if (!name.trim()) {
      return;
    }
    setSaving(true);
    setError(null);
    try {
      if (editing) {
        await api.patch(`/api/cover-letters/${editing.id}`, { name: name.trim(), body });
      } else {
        await api.post('/api/cover-letters', { name: name.trim(), body });
      }
      setShowModal(false);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to save the cover letter.');
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(letter: CoverLetter) {
    if (!window.confirm(`Delete cover letter "${letter.name}"?`)) {
      return;
    }
    setError(null);
    try {
      await api.delete(`/api/cover-letters/${letter.id}`);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to delete the cover letter.');
    }
  }

  return (
    <div className="page">
      <div className="page-header">
        <h1>Cover Letters</h1>
        <button type="button" className="btn btn-primary" onClick={() => openModal(null)}>
          New cover letter
        </button>
      </div>
      <ErrorBanner message={error} />

      {loading ? (
        <div className="spinner" aria-label="Loading" />
      ) : letters.length === 0 ? (
        <div className="empty-state">
          <p>No cover letters yet.</p>
          <button type="button" className="btn btn-primary" onClick={() => openModal(null)}>
            Write your first cover letter
          </button>
        </div>
      ) : (
        <ul className="card-list">
          {letters.map((letter) => (
            <li key={letter.id} className="card">
              <div className="card-title-row">
                <span className="card-title">{letter.name}</span>
                <div className="btn-group">
                  <button type="button" className="btn btn-sm btn-ghost" onClick={() => openModal(letter)}>
                    Edit
                  </button>
                  <button
                    type="button"
                    className="btn btn-sm btn-danger-ghost"
                    onClick={() => void handleDelete(letter)}
                  >
                    Delete
                  </button>
                </div>
              </div>
              <div className="card-meta">Added {formatDate(letter.createdAt)}</div>
              {letter.body && <p className="pre-wrap letter-preview">{letter.body}</p>}
            </li>
          ))}
        </ul>
      )}

      {showModal && (
        <Modal title={editing ? 'Edit cover letter' : 'New cover letter'} onClose={() => setShowModal(false)}>
          <form onSubmit={handleSave} className="form">
            <label className="field">
              <span>Name</span>
              <input
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                autoFocus
                placeholder="e.g. Generic engineering"
              />
            </label>
            <label className="field">
              <span>Body</span>
              <textarea
                rows={12}
                value={body}
                onChange={(e) => setBody(e.target.value)}
                placeholder="Write the cover letter. Use {company} and {title} as placeholders."
              />
            </label>
            <div className="form-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowModal(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving}>
                {saving ? 'Saving…' : 'Save'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
