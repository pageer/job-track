import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api, ApiError } from '../api';
import type { JobSearchDetail, JobStatus } from '../types';
import { JOB_STATUSES, JOB_STATUS_LABELS } from '../types';
import { formatDate, toDateInputValue } from '../utils';
import ErrorBanner from '../components/ErrorBanner';
import Modal from '../components/Modal';

interface EditFormState {
  name: string;
  startDate: string;
  endDate: string;
}

interface JobFormState {
  title: string;
  company: string;
  status: JobStatus | '';
  descriptionHtml: string;
  descriptionUrl: string;
}

const emptyJobForm: JobFormState = {
  title: '',
  company: '',
  status: '',
  descriptionHtml: '',
  descriptionUrl: '',
};

const defaultActiveStatuses = new Set<JobStatus>(
  JOB_STATUSES.filter((s) => s !== 'rejected')
);

type SortKey = 'createdAt' | 'actionDate';

export default function SearchDetailPage() {
  const { searchId } = useParams<{ searchId: string }>();
  const navigate = useNavigate();

  const [search, setSearch] = useState<JobSearchDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [showEdit, setShowEdit] = useState(false);
  const [editForm, setEditForm] = useState<EditFormState>({ name: '', startDate: '', endDate: '' });
  const [savingEdit, setSavingEdit] = useState(false);

  const [showJobModal, setShowJobModal] = useState(false);
  const [jobForm, setJobForm] = useState<JobFormState>(emptyJobForm);
  const [savingJob, setSavingJob] = useState(false);

  const [activeStatuses, setActiveStatuses] = useState<Set<JobStatus>>(defaultActiveStatuses);
  const [sortBy, setSortBy] = useState<SortKey>('createdAt');

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.get<JobSearchDetail>(`/api/job-searches/${searchId}`);
      setSearch(data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to load the job search.');
    } finally {
      setLoading(false);
    }
  }, [searchId]);

  useEffect(() => {
    void load();
  }, [load]);

  async function handleEditSubmit(e: FormEvent) {
    e.preventDefault();
    if (!editForm.name.trim() || !editForm.startDate) {
      return;
    }
    setSavingEdit(true);
    setError(null);
    try {
      const body: Record<string, string> = {
        name: editForm.name.trim(),
        startDate: editForm.startDate,
      };
      if (editForm.endDate) {
        body.endDate = editForm.endDate;
      }
      await api.patch(`/api/job-searches/${searchId}`, body);
      setShowEdit(false);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to update the job search.');
    } finally {
      setSavingEdit(false);
    }
  }

  async function handleDeleteSearch() {
    if (!window.confirm('Delete this job search and all its jobs?')) {
      return;
    }
    setError(null);
    try {
      await api.delete(`/api/job-searches/${searchId}`);
      navigate('/');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to delete the job search.');
    }
  }

  async function handleJobSubmit(e: FormEvent) {
    e.preventDefault();
    if (!jobForm.title.trim() || !jobForm.company.trim()) {
      return;
    }
    setSavingJob(true);
    setError(null);
    try {
      const body: Record<string, unknown> = {
        title: jobForm.title.trim(),
        company: jobForm.company.trim(),
      };
      if (jobForm.status) {
        body.status = jobForm.status;
      }
      if (jobForm.descriptionHtml.trim()) {
        body.descriptionHtml = jobForm.descriptionHtml;
      }
      if (jobForm.descriptionUrl.trim()) {
        body.descriptionUrl = jobForm.descriptionUrl;
      }
      await api.post(`/api/job-searches/${searchId}/jobs`, body);
      setShowJobModal(false);
      setJobForm(emptyJobForm);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to create the job.');
    } finally {
      setSavingJob(false);
    }
  }

  function toggleStatus(status: JobStatus) {
    setActiveStatuses((prev) => {
      const next = new Set(prev);
      if (next.has(status)) {
        next.delete(status);
      } else {
        next.add(status);
      }
      return next;
    });
  }

  function selectAllStatuses() {
    setActiveStatuses(new Set(JOB_STATUSES));
  }

  function selectDefaultStatuses() {
    setActiveStatuses(defaultActiveStatuses);
  }

  const jobs = search?.jobs ?? [];

  const jobCounts = jobs.reduce<Record<string, number>>((acc, j) => {
    acc[j.status] = (acc[j.status] ?? 0) + 1;
    return acc;
  }, {});

  const filteredAndSorted = useMemo(() => {
    const filtered = jobs.filter((j) => activeStatuses.has(j.status));

    return [...filtered].sort((a, b) => {
      if (sortBy === 'actionDate') {
        const aDate = a.actionDate ?? '';
        const bDate = b.actionDate ?? '';
        if (aDate && bDate) return bDate.localeCompare(aDate);
        if (aDate) return -1;
        if (bDate) return 1;
        return b.createdAt.localeCompare(a.createdAt);
      }
      return b.createdAt.localeCompare(a.createdAt);
    });
  }, [jobs, activeStatuses, sortBy]);

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <Link to="/" className="back-link">
            &larr; All job searches
          </Link>
          <h1>{search?.name ?? 'Job search'}</h1>
          {search && (
            <p className="page-subtitle">
              {formatDate(search.startDate)}
              {search.endDate ? ` — ${formatDate(search.endDate)}` : ' — ongoing'}
            </p>
          )}
        </div>
        <div className="btn-group">
          {search && (
            <>
              <button
                type="button"
                className="btn btn-ghost"
                onClick={() => {
                  setEditForm({
                    name: search.name,
                    startDate: toDateInputValue(search.startDate),
                    endDate: toDateInputValue(search.endDate),
                  });
                  setShowEdit(true);
                }}
              >
                Edit
              </button>
              <button type="button" className="btn btn-danger-ghost" onClick={() => void handleDeleteSearch()}>
                Delete
              </button>
            </>
          )}
        </div>
      </div>

      {!loading && jobs.length > 0 && (
        <div className="filter-bar">
          <span className="filter-label">Status:</span>
          {JOB_STATUSES.map((status) => {
            const count = jobCounts[status] ?? 0;
            const active = activeStatuses.has(status);
            return (
              <button
                key={status}
                type="button"
                className={`badge badge-${status} badge-clickable ${active ? 'badge-active' : 'badge-inactive'}`}
                onClick={() => toggleStatus(status)}
                disabled={count === 0}
              >
                {JOB_STATUS_LABELS[status]}: {count}
              </button>
            );
          })}
          <button type="button" className="btn btn-sm btn-ghost" onClick={selectAllStatuses}>
            All
          </button>
          <button type="button" className="btn btn-sm btn-ghost" onClick={selectDefaultStatuses}>
            Default
          </button>
        </div>
      )}

      <ErrorBanner message={error} />

      <div className="page-toolbar">
        <h2>Jobs</h2>
        <div className="btn-group">
          {jobs.length > 0 && (
            <div className="sort-group">
              <span className="filter-label">Sort:</span>
              <button
                type="button"
                className={`btn btn-sm ${sortBy === 'createdAt' ? 'btn-primary' : 'btn-ghost'}`}
                onClick={() => setSortBy('createdAt')}
              >
                Date added
              </button>
              <button
                type="button"
                className={`btn btn-sm ${sortBy === 'actionDate' ? 'btn-primary' : 'btn-ghost'}`}
                onClick={() => setSortBy('actionDate')}
              >
                Date applied
              </button>
            </div>
          )}
          <button type="button" className="btn btn-primary" onClick={() => setShowJobModal(true)}>
            Add job
          </button>
        </div>
      </div>

      {loading ? (
        <div className="spinner" aria-label="Loading" />
      ) : filteredAndSorted.length === 0 ? (
        <div className="empty-state">
          {jobs.length === 0 ? (
            <>
              <p>No jobs in this search yet.</p>
              <button type="button" className="btn btn-primary" onClick={() => setShowJobModal(true)}>
                Add your first job
              </button>
            </>
          ) : (
            <p>No jobs match the selected filters.</p>
          )}
        </div>
      ) : (
        <ul className="card-list">
          {filteredAndSorted.map((job) => (
            <li key={job.id}>
              <Link to={`/jobs/${job.id}`} className="card card-link">
                <div className="card-title-row">
                  <span className="card-title">{job.company}</span>
                  <span className={`badge badge-${job.status}`}>{JOB_STATUS_LABELS[job.status]}</span>
                </div>
                <div className="card-meta">{job.title}</div>
                <div className="card-dates">
                  <span>Added {formatDate(job.createdAt)}</span>
                  {job.actionDate && <span>Applied {formatDate(job.actionDate)}</span>}
                </div>
              </Link>
            </li>
          ))}
        </ul>
      )}

      {showEdit && search && (
        <Modal title="Edit job search" onClose={() => setShowEdit(false)}>
          <form onSubmit={handleEditSubmit} className="form">
            <label className="field">
              <span>Name</span>
              <input
                value={editForm.name}
                onChange={(e) => setEditForm({ ...editForm, name: e.target.value })}
                required
                autoFocus
              />
            </label>
            <label className="field">
              <span>Start date</span>
              <input
                type="date"
                value={editForm.startDate}
                onChange={(e) => setEditForm({ ...editForm, startDate: e.target.value })}
                required
              />
            </label>
            <label className="field">
              <span>End date (optional)</span>
              <input
                type="date"
                value={editForm.endDate}
                min={editForm.startDate || undefined}
                onChange={(e) => setEditForm({ ...editForm, endDate: e.target.value })}
              />
            </label>
            <div className="form-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowEdit(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={savingEdit}>
                {savingEdit ? 'Saving…' : 'Save'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {showJobModal && (
        <Modal title="Add job" onClose={() => setShowJobModal(false)}>
          <form onSubmit={handleJobSubmit} className="form">
            <label className="field">
              <span>Title</span>
              <input
                value={jobForm.title}
                onChange={(e) => setJobForm({ ...jobForm, title: e.target.value })}
                required
                autoFocus
              />
            </label>
            <label className="field">
              <span>Company</span>
              <input
                value={jobForm.company}
                onChange={(e) => setJobForm({ ...jobForm, company: e.target.value })}
                required
              />
            </label>
            <label className="field">
              <span>Status</span>
              <select
                value={jobForm.status}
                onChange={(e) => setJobForm({ ...jobForm, status: e.target.value as JobStatus | '' })}
              >
                <option value="">Investigating (default)</option>
                {Object.entries(JOB_STATUS_LABELS).map(([value, label]) => (
                  <option key={value} value={value}>
                    {label}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Description URL (optional)</span>
              <input
                type="url"
                value={jobForm.descriptionUrl}
                onChange={(e) => setJobForm({ ...jobForm, descriptionUrl: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Description (HTML, optional)</span>
              <textarea
                rows={5}
                value={jobForm.descriptionHtml}
                onChange={(e) => setJobForm({ ...jobForm, descriptionHtml: e.target.value })}
              />
            </label>
            <div className="form-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowJobModal(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={savingJob}>
                {savingJob ? 'Creating…' : 'Add job'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
