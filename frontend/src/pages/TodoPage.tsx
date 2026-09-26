import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { api, ApiError } from '../api';
import type { Todo } from '../types';
import { formatDateKey } from '../utils';
import { renderMarkdown } from '../markdown';
import ErrorBanner from '../components/ErrorBanner';
import Modal from '../components/Modal';

interface TodoFormState {
  description: string;
  targetDate: string;
  details: string;
}

interface CompleteFormState {
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

function tomorrowInputValue(): string {
  const today = todayInputValue();
  const [y, m, d] = today.split('-').map(Number);
  return formatUtcKey(new Date(Date.UTC(y, m - 1, d) + 86400000));
}

function formatUtcKey(date: Date): string {
  const year = date.getUTCFullYear();
  const month = String(date.getUTCMonth() + 1).padStart(2, '0');
  const day = String(date.getUTCDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function emptyTodoForm(): TodoFormState {
  return {
    description: '',
    targetDate: tomorrowInputValue(),
    details: '',
  };
}

function formFromTodo(todo: Todo): TodoFormState {
  return {
    description: todo.description,
    targetDate: todo.targetDate.slice(0, 10),
    details: todo.details ?? '',
  };
}

function emptyCompleteForm(
  description: string,
  details: string,
): CompleteFormState {
  return {
    description,
    date: todayInputValue(),
    hoursSpent: '',
    details,
  };
}

type DueStatus = 'overdue' | 'today' | 'upcoming';

function dueStatus(targetDate: string, today: string): DueStatus {
  if (targetDate < today) {
    return 'overdue';
  }
  if (targetDate === today) {
    return 'today';
  }
  return 'upcoming';
}

function dueLabel(targetDate: string, today: string): string {
  const status = dueStatus(targetDate, today);
  if (status === 'overdue') {
    return `Overdue · ${formatDateKey(targetDate)}`;
  }
  if (status === 'today') {
    return 'Due today';
  }
  return `Due ${formatDateKey(targetDate)}`;
}

export default function TodoPage() {
  const [todos, setTodos] = useState<Todo[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [showModal, setShowModal] = useState(false);
  const [editing, setEditing] = useState<Todo | null>(null);
  const [form, setForm] = useState<TodoFormState>(emptyTodoForm());
  const [saving, setSaving] = useState(false);

  const [completing, setCompleting] = useState<Todo | null>(null);
  const [showCompleteModal, setShowCompleteModal] = useState(false);
  const [completeForm, setCompleteForm] = useState<CompleteFormState>(
    emptyCompleteForm('', ''),
  );
  const [completingSaving, setCompletingSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.get<Todo[]>('/api/todos');
      setTodos(data);
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Failed to load to-dos.',
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  const today = todayInputValue();

  function openModal(todo: Todo | null) {
    setEditing(todo);
    setForm(todo ? formFromTodo(todo) : emptyTodoForm());
    setShowModal(true);
  }

  function openComplete(todo: Todo) {
    setCompleting(todo);
    setCompleteForm(emptyCompleteForm(todo.description, todo.details ?? ''));
    setShowCompleteModal(true);
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    const description = form.description.trim();
    if (!description || !form.targetDate) {
      return;
    }
    setSaving(true);
    setError(null);
    try {
      const body = {
        description,
        targetDate: form.targetDate,
        details: form.details.trim(),
      };
      if (editing) {
        await api.patch(`/api/todos/${editing.id}`, body);
      } else {
        await api.post('/api/todos', body);
      }
      setShowModal(false);
      await load();
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Failed to save the to-do.',
      );
    } finally {
      setSaving(false);
    }
  }

  async function handleComplete(e: FormEvent) {
    e.preventDefault();
    if (!completing) {
      return;
    }
    const description = completeForm.description.trim();
    const hours = Number(completeForm.hoursSpent);
    if (!description || !completeForm.date) {
      return;
    }
    if (!Number.isFinite(hours) || hours <= 0 || hours > 24) {
      setError('Hours spent must be a number greater than 0 and at most 24.');
      return;
    }
    setCompletingSaving(true);
    setError(null);
    try {
      await api.post(`/api/todos/${completing.id}/complete`, {
        description,
        date: completeForm.date,
        hoursSpent: hours,
        details: completeForm.details.trim(),
      });
      setShowCompleteModal(false);
      setCompleting(null);
      await load();
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Failed to complete the to-do.',
      );
    } finally {
      setCompletingSaving(false);
    }
  }

  async function handleDelete(todo: Todo) {
    if (!window.confirm(`Delete to-do "${todo.description}"?`)) {
      return;
    }
    setError(null);
    try {
      await api.delete(`/api/todos/${todo.id}`);
      await load();
    } catch (err) {
      setError(
        err instanceof ApiError ? err.message : 'Failed to delete the to-do.',
      );
    }
  }

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1>To-dos</h1>
          <p className="page-subtitle">
            Plan your next steps. Mark one done to log it as a job search
            activity.
          </p>
        </div>
        <button
          type="button"
          className="btn btn-primary"
          onClick={() => openModal(null)}
        >
          Add to-do
        </button>
      </div>
      <ErrorBanner message={error} />

      {loading ? (
        <div className="spinner" aria-label="Loading" />
      ) : todos.length === 0 ? (
        <div className="empty-state">
          <p>No to-dos yet.</p>
          <button
            type="button"
            className="btn btn-primary"
            onClick={() => openModal(null)}
          >
            Add your first to-do
          </button>
        </div>
      ) : (
        <ul className="todo-list">
          {todos.map((todo) => {
            const targetDate = todo.targetDate.slice(0, 10);
            const status = dueStatus(targetDate, today);
            return (
              <li key={todo.id} className={`todo-card todo-${status}`}>
                <div className="todo-card-main">
                  <button
                    type="button"
                    className="todo-done-btn"
                    aria-label={`Mark "${todo.description}" as done`}
                    title="Mark as done"
                    onClick={() => openComplete(todo)}
                  >
                    <span aria-hidden="true" />
                  </button>
                  <div className="todo-card-content">
                    <div className="card-title-row">
                      <span className="card-title">{todo.description}</span>
                      <div className="btn-group">
                        <span className={`todo-due-badge todo-due-${status}`}>
                          {dueLabel(targetDate, today)}
                        </span>
                        <button
                          type="button"
                          className="btn btn-sm btn-ghost"
                          onClick={() => openModal(todo)}
                        >
                          Edit
                        </button>
                        <button
                          type="button"
                          className="btn btn-sm btn-danger-ghost"
                          onClick={() => void handleDelete(todo)}
                        >
                          Delete
                        </button>
                      </div>
                    </div>
                    {todo.details && (
                      <div
                        className="markdown todo-details"
                        dangerouslySetInnerHTML={{
                          __html: renderMarkdown(todo.details),
                        }}
                      />
                    )}
                  </div>
                </div>
              </li>
            );
          })}
        </ul>
      )}

      {showModal && (
        <Modal
          title={editing ? 'Edit to-do' : 'Add to-do'}
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
                placeholder="e.g. Send follow-up to Acme"
              />
            </label>
            <label className="field">
              <span>Target date</span>
              <input
                type="date"
                value={form.targetDate}
                onChange={(e) =>
                  setForm({ ...form, targetDate: e.target.value })
                }
                required
              />
            </label>
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

      {showCompleteModal && completing && (
        <Modal title="Mark as done" onClose={() => setShowCompleteModal(false)}>
          <form onSubmit={handleComplete} className="form">
            <p className="form-hint">
              This to-do will be logged as a job search activity.
            </p>
            <label className="field">
              <span>Description</span>
              <input
                value={completeForm.description}
                onChange={(e) =>
                  setCompleteForm({
                    ...completeForm,
                    description: e.target.value,
                  })
                }
                required
                autoFocus
              />
            </label>
            <div className="field-row">
              <label className="field">
                <span>Date</span>
                <input
                  type="date"
                  value={completeForm.date}
                  onChange={(e) =>
                    setCompleteForm({ ...completeForm, date: e.target.value })
                  }
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
                  value={completeForm.hoursSpent}
                  onChange={(e) =>
                    setCompleteForm({
                      ...completeForm,
                      hoursSpent: e.target.value,
                    })
                  }
                  required
                  placeholder="e.g. 0.5"
                />
              </label>
            </div>
            <label className="field">
              <span>Details (optional)</span>
              <textarea
                rows={4}
                value={completeForm.details}
                onChange={(e) =>
                  setCompleteForm({
                    ...completeForm,
                    details: e.target.value,
                  })
                }
              />
            </label>
            <div className="form-actions">
              <button
                type="button"
                className="btn btn-ghost"
                onClick={() => setShowCompleteModal(false)}
              >
                Cancel
              </button>
              <button
                type="submit"
                className="btn btn-primary"
                disabled={completingSaving}
              >
                {completingSaving ? 'Saving…' : 'Complete'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
