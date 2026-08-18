import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { api, ApiError } from '../api';
import type { JobSearchDetail, JobStatus } from '../types';
import { JOB_STATUS_LABELS } from '../types';
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

  const jobs = search?.jobs ?? [];
  const jobCounts = jobs.reduce<Record<string, number>>((acc, j) => {
    acc[j.status] = (acc[j.status] ?? 0) + 1;
    return acc;
  }, {});

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
        <div className="stat-row">
          {Object.entries(jobCounts).map(([status, count]) => (
            <span key={status} className={`badge badge-${status}`}>
              {JOB_STATUS_LABELS[status as JobStatus]}: {count}
            </span>
          ))}
        </div>
      )}

      <ErrorBanner message={error} />

      <div className="page-toolbar">
        <h2>Jobs</h2>
        <button type="button" className="btn btn-primary" onClick={() => setShowJobModal(true)}>
          Add job
        </button>
      </div>

      {loading ? (
        <div className="spinner" aria-label="Loading" />
      ) : jobs.length === 0 ? (
        <div className="empty-state">
          <p>No jobs in this search yet.</p>
          <button type="button" className="btn btn-primary" onClick={() => setShowJobModal(true)}>
            Add your first job
          </button>
        </div>
      ) : (
        <ul className="card-list">
          {jobs.map((job) => (
            <li key={job.id}>
              <Link to={`/jobs/${job.id}`} className="card card-link">
                <div className="card-title-row">
                  <span className="card-title">{job.title}</span>
                  <span className={`badge badge-${job.status}`}>{JOB_STATUS_LABELS[job.status]}</span>
                </div>
                <div className="card-meta">{job.company}</div>
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
