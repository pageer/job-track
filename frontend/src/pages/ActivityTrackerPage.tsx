import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { api, ApiError } from '../api';
import type { JobActivity } from '../types';
import { formatDateKey, formatHours } from '../utils';
import { groupActivities } from '../activityGroups';
import { renderMarkdown } from '../markdown';
import ErrorBanner from '../components/ErrorBanner';
import Modal from '../components/Modal';

interface ActivityFormState {
  description: string;
  date: string;
  hoursSpent: string;
  details: string;
}

function todayInputValue(): string {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function emptyForm(): ActivityFormState {
  return {
    description: '',
    date: todayInputValue(),
    hoursSpent: '',
    details: '',
  };
}

function formFromActivity(activity: JobActivity): ActivityFormState {
  return {
    description: activity.description,
    date: activity.date.slice(0, 10),
    hoursSpent: String(activity.hoursSpent),
    details: activity.details ?? '',
  };
}

export default function ActivityTrackerPage() {
  const [activities, setActivities] = useState<JobActivity[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<JobActivity | null>(null);
  const [form, setForm] = useState<ActivityFormState>(emptyForm());
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.get<JobActivity[]>('/api/job-activities');
      setActivities(data);
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : 'Failed to load job activities.',
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  function openModal(activity: JobActivity | null) {
    setEditing(activity);
    setForm(activity ? formFromActivity(activity) : emptyForm());
    setShowModal(true);
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    const description = form.description.trim();
    const hours = Number(form.hoursSpent);
    if (!description || !form.date) {
      return;
    }
    if (!Number.isFinite(hours) || hours <= 0 || hours > 24) {
      setError('Hours spent must be a number greater than 0 and at most 24.');
      return;
    }
    setSaving(true);
    setError(null);
    try {
      const body = {
        description,
        date: form.date,
        hoursSpent: hours,
        details: form.details.trim(),
      };
      if (editing) {
        await api.patch(`/api/job-activities/${editing.id}`, body);
      } else {
        await api.post('/api/job-activities', body);
      }
      setShowModal(false);
      await load();
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Failed to save the activity.',
      );
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(activity: JobActivity) {
    if (
      !window.confirm(
        `Delete activity "${activity.description}" on ${formatDateKey(activity.date)}?`,
      )
    ) {
      return;
    }
    setError(null);
    try {
      await api.delete(`/api/job-activities/${activity.id}`);
      await load();
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : 'Failed to delete the activity.',
      );
    }
  }

  const weeks = groupActivities(activities);

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1>Activity Tracker</h1>
          <p className="page-subtitle">
            Goal: log 5+ activities a day and 25–35 hours a week.
          </p>
        </div>
        <button
          type="button"
          className="btn btn-primary"
          onClick={() => openModal(null)}
        >
          Log activity
        </button>
      </div>
      <ErrorBanner message={error} />

      {loading ? (
        <div className="spinner" aria-label="Loading" />
      ) : activities.length === 0 ? (
        <div className="empty-state">
          <p>No activities logged yet.</p>
          <button
            type="button"
            className="btn btn-primary"
            onClick={() => openModal(null)}
          >
            Log your first activity
          </button>
        </div>
      ) : (
        weeks.map((week) => (
          <section key={week.key} className="activity-week">
            <header className="activity-week-header">
              <h2>
                Week of {formatDateKey(week.startDate)} –{' '}
                {formatDateKey(week.endDate)}
              </h2>
              <span className="hours-badge">
                {formatHours(week.totalHours)}
              </span>
            </header>

            {week.days.map((day) => (
              <div key={day.key} className="activity-day">
                <div className="activity-day-header">
                  <span className="activity-day-label">
                    {formatDateKey(day.key)}
                  </span>
                  <span className="hours-badge">
                    {formatHours(day.totalHours)}
                  </span>
                </div>
                <ul className="card-list">
                  {day.activities.map((activity) => (
                    <li key={activity.id} className="card">
                      <div className="card-title-row">
                        <span className="card-title">
                          {activity.description}
                        </span>
                        <div className="btn-group">
                          <span className="hours-badge">
                            {formatHours(activity.hoursSpent)}
                          </span>
                          <button
                            type="button"
                            className="btn btn-sm btn-ghost"
                            onClick={() => openModal(activity)}
                          >
                            Edit
                          </button>
                          <button
                            type="button"
                            className="btn btn-sm btn-danger-ghost"
                            onClick={() => void handleDelete(activity)}
                          >
                            Delete
                          </button>
                        </div>
                      </div>
                      {activity.details && (
                        <div
                          className="markdown activity-details"
                          dangerouslySetInnerHTML={{
                            __html: renderMarkdown(activity.details),
                          }}
                        />
                      )}
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </section>
        ))
      )}

      {showModal && (
        <Modal
          title={editing ? 'Edit activity' : 'Log activity'}
          onClose={() => setShowModal(false)}
        >
          <form onSubmit={handleSubmit} className="form">
            <label className="field">
              <span>Description</span>
              <input
                value={form.description}
                onChange={(e) =>
                  setForm({ ...form, description: e.target.value })
                }
                required
                autoFocus
                placeholder="e.g. Applied to 3 positions"
              />
            </label>
            <div className="field-row">
              <label className="field">
                <span>Date</span>
                <input
                  type="date"
                  value={form.date}
                  onChange={(e) => setForm({ ...form, date: e.target.value })}
                  required
                />
              </label>
              <label className="field">
                <span>Hours spent</span>
                <input
                  type="number"
                  min="0.01"
                  max="24"
                  step="any"
                  value={form.hoursSpent}
                  onChange={(e) =>
                    setForm({ ...form, hoursSpent: e.target.value })
                  }
                  required
                  placeholder="e.g. 1.5"
                />
              </label>
            </div>
            <label className="field">
              <span>Details (optional)</span>
              <textarea
                rows={4}
                value={form.details}
                onChange={(e) => setForm({ ...form, details: e.target.value })}
                placeholder="Plain text or Markdown"
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
