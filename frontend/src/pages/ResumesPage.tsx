import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { api, ApiError } from '../api';
import type { Resume } from '../types';
import { formatFileSize, formatDate } from '../utils';
import ErrorBanner from '../components/ErrorBanner';
import Modal from '../components/Modal';

interface CreateFormState {
  name: string;
  linkUrl: string;
  file: File | null;
}

const emptyForm: CreateFormState = { name: '', linkUrl: '', file: null };

export default function ResumesPage() {
  const [resumes, setResumes] = useState<Resume[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState<CreateFormState>(emptyForm);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.get<Resume[]>('/api/resumes');
      setResumes(data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to load resumes.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleCreate(e: FormEvent) {
    e.preventDefault();
    if (!form.name.trim() || (!form.file && !form.linkUrl.trim())) {
      return;
    }
    setSaving(true);
    setError(null);
    try {
      if (form.file) {
        const fd = new FormData();
        fd.append('name', form.name.trim());
        fd.append('file', form.file);
        await api.postForm<Resume>('/api/resumes', fd);
      } else {
        await api.post<Resume>('/api/resumes', {
          name: form.name.trim(),
          kind: 'link',
          linkUrl: form.linkUrl.trim(),
        });
      }
      setShowModal(false);
      setForm(emptyForm);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to create the resume.');
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(resume: Resume) {
    if (!window.confirm(`Delete resume "${resume.name}"?`)) {
      return;
    }
    setError(null);
    try {
      await api.delete(`/api/resumes/${resume.id}`);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to delete the resume.');
    }
  }

  const canSubmit = form.name.trim().length > 0 && (form.file !== null || form.linkUrl.trim().length > 0);

  return (
    <div className="page">
      <div className="page-header">
        <h1>Resumes</h1>
        <button type="button" className="btn btn-primary" onClick={() => setShowModal(true)}>
          New resume
        </button>
      </div>
      <ErrorBanner message={error} />

      {loading ? (
        <div className="spinner" aria-label="Loading" />
      ) : resumes.length === 0 ? (
        <div className="empty-state">
          <p>No resumes yet.</p>
          <button type="button" className="btn btn-primary" onClick={() => setShowModal(true)}>
            Add your first resume
          </button>
        </div>
      ) : (
        <ul className="card-list">
          {resumes.map((r) => (
            <li key={r.id} className="card">
              <div className="card-title-row">
                <span className="card-title">{r.name}</span>
                <div className="btn-group">
                  <button type="button" className="btn btn-sm btn-danger-ghost" onClick={() => void handleDelete(r)}>
                    Delete
                  </button>
                </div>
              </div>
              <div className="card-meta">
                {r.kind === 'file' ? (
                  <>
                    <a href={`/api/resumes/${r.id}/download`} target="_blank" rel="noreferrer">
                      {r.fileName}
                    </a>{' '}
                    ({formatFileSize(r.fileSize)})
                  </>
                ) : (
                  <a href={r.linkUrl ?? '#'} target="_blank" rel="noreferrer">
                    {r.linkUrl}
                  </a>
                )}
                <span> &middot; added {formatDate(r.createdAt)}</span>
              </div>
            </li>
          ))}
        </ul>
      )}

      {showModal && (
        <Modal title="New resume" onClose={() => setShowModal(false)}>
          <form onSubmit={handleCreate} className="form">
            <label className="field">
              <span>Name</span>
              <input
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                required
                autoFocus
                placeholder="e.g. Software Engineer"
              />
            </label>
            <label className="field">
              <span>Upload a file</span>
              <input
                type="file"
                onChange={(e) => {
                  const file = e.target.files?.[0] ?? null;
                  setForm({ ...form, file });
                }}
              />
            </label>
            <div className="divider">or</div>
            <label className="field">
              <span>Use a link</span>
              <input
                type="url"
                placeholder="https://example.com/my-resume.pdf"
                value={form.linkUrl}
                onChange={(e) => setForm({ ...form, linkUrl: e.target.value })}
              />
            </label>
            <div className="form-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowModal(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={saving || !canSubmit}>
                {saving ? 'Creating…' : 'Create'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
