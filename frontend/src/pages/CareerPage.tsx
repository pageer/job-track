import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { api, ApiError } from '../api';
import type { Accomplishment } from '../types';
import {
  firstLine,
  formatYearMonth,
  sortAccomplishmentsDescending,
} from '../career';
import ErrorBanner from '../components/ErrorBanner';
import Modal from '../components/Modal';

interface AccomplishmentFormState {
  date: string;
  company: string;
  description: string;
}

function emptyForm(): AccomplishmentFormState {
  return { date: '', company: '', description: '' };
}

function formFromAccomplishment(
  accomplishment: Accomplishment,
): AccomplishmentFormState {
  return {
    date: accomplishment.date.slice(0, 10),
    company: accomplishment.company,
    description: accomplishment.description,
  };
}

function AccomplishmentEntry({
  accomplishment,
  onEdit,
  onDelete,
}: {
  accomplishment: Accomplishment;
  onEdit: (accomplishment: Accomplishment) => void;
  onDelete: (accomplishment: Accomplishment) => void;
}) {
  const [expanded, setExpanded] = useState(false);
  const { company, date, description } = accomplishment;

  return (
    <li className="accomplishment-card">
      <div className="accomplishment-header">
        <span className="accomplishment-date">
          <time dateTime={date.slice(0, 10)}>{formatYearMonth(date)}</time>
        </span>
        <span className="accomplishment-company">{company}</span>
      </div>
      <p className="accomplishment-description">
        {expanded ? description : firstLine(description)}
      </p>
      <div className="accomplishment-actions">
        <button
          type="button"
          className="btn btn-sm btn-ghost"
          aria-expanded={expanded}
          onClick={() => setExpanded((value) => !value)}
        >
          {expanded ? 'Show less' : 'Show more'}
        </button>
        <div className="btn-group">
          <button
            type="button"
            className="btn btn-sm btn-ghost"
            onClick={() => onEdit(accomplishment)}
          >
            Edit
          </button>
          <button
            type="button"
            className="btn btn-sm btn-danger-ghost"
            onClick={() => onDelete(accomplishment)}
          >
            Delete
          </button>
        </div>
      </div>
    </li>
  );
}

export default function CareerPage() {
  const [accomplishments, setAccomplishments] = useState<Accomplishment[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<Accomplishment | null>(null);
  const [form, setForm] = useState<AccomplishmentFormState>(emptyForm());
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.get<Accomplishment[]>('/api/accomplishments');
      setAccomplishments(sortAccomplishmentsDescending(data));
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : 'Failed to load accomplishments.',
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  function openModal(accomplishment: Accomplishment | null) {
    setEditing(accomplishment);
    setForm(
      accomplishment ? formFromAccomplishment(accomplishment) : emptyForm(),
    );
    setShowModal(true);
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    const company = form.company.trim();
    const description = form.description.trim();
    if (!form.date || !company || !description) {
      return;
    }
    setSaving(true);
    setError(null);
    try {
      const body = { date: form.date, company, description };
      if (editing) {
        await api.patch(`/api/accomplishments/${editing.id}`, body);
      } else {
        await api.post('/api/accomplishments', body);
      }
      setShowModal(false);
      await load();
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : 'Failed to save the accomplishment.',
      );
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(accomplishment: Accomplishment) {
    const firstLinePreview = firstLine(accomplishment.description);
    if (!window.confirm(`Delete accomplishment "${firstLinePreview}"?`)) {
      return;
    }
    setError(null);
    try {
      await api.delete(`/api/accomplishments/${accomplishment.id}`);
      await load();
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : 'Failed to delete the accomplishment.',
      );
    }
  }

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1>Career</h1>
          <p className="page-subtitle">
            Track accomplishments and projects for interview talking points and
            resume updates.
          </p>
        </div>
        <button
          type="button"
          className="btn btn-primary"
          onClick={() => openModal(null)}
        >
          Add accomplishment
        </button>
      </div>
      <ErrorBanner message={error} />

      {loading ? (
        <div className="spinner" aria-label="Loading" />
      ) : accomplishments.length === 0 ? (
        <div className="empty-state">
          <p>No accomplishments yet.</p>
          <button
            type="button"
            className="btn btn-primary"
            onClick={() => openModal(null)}
          >
            Add your first accomplishment
          </button>
        </div>
      ) : (
        <ul className="accomplishment-list">
          {accomplishments.map((accomplishment) => (
            <AccomplishmentEntry
              key={accomplishment.id}
              accomplishment={accomplishment}
              onEdit={openModal}
              onDelete={(item) => void handleDelete(item)}
            />
          ))}
        </ul>
      )}

      {showModal && (
        <Modal
          title={editing ? 'Edit accomplishment' : 'Add accomplishment'}
          onClose={() => setShowModal(false)}
        >
          <form onSubmit={handleSubmit} className="form">
            <label className="field">
              <span>Date</span>
              <input
                type="date"
                value={form.date}
                onChange={(e) => setForm({ ...form, date: e.target.value })}
                required
                autoFocus
              />
            </label>
            <label className="field">
              <span>Company name</span>
              <input
                value={form.company}
                onChange={(e) => setForm({ ...form, company: e.target.value })}
                required
                placeholder="e.g. Acme Inc"
              />
            </label>
            <label className="field">
              <span>Description</span>
              <textarea
                rows={6}
                value={form.description}
                onChange={(e) =>
                  setForm({ ...form, description: e.target.value })
                }
                required
                placeholder="What did you accomplish? Add projects, impact, talking points…"
              />
            </label>
            <div className="form-actions">
              <button
                type="button"
                className="btn btn-ghost"
                onClick={() => setShowModal(false)}
              >
                Cancel
              </button>
              <button
                type="submit"
                className="btn btn-primary"
                disabled={saving}
              >
                {saving ? 'Saving…' : 'Save'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
