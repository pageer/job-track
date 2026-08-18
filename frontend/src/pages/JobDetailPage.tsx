import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { Link, useParams } from 'react-router-dom';
import { api, ApiError } from '../api';
import type { Application, Interview, JobDetail, JobStatus } from '../types';
import { JOB_STATUS_LABELS } from '../types';
import { formatDateTime, formatFileSize, formatDate, toDateTimeInputValue } from '../utils';
import ErrorBanner from '../components/ErrorBanner';
import Modal from '../components/Modal';
import RichTextEditor from '../components/RichTextEditor';

interface JobFormState {
  title: string;
  company: string;
  status: JobStatus;
  descriptionHtml: string;
  descriptionUrl: string;
}

interface InterviewFormState {
  date: string;
  interviewers: string;
  notes: string;
}

const emptyInterviewForm: InterviewFormState = { date: '', interviewers: '', notes: '' };

export default function JobDetailPage() {
  const { jobId } = useParams<{ jobId: string }>();
  const [job, setJob] = useState<JobDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [showEditJob, setShowEditJob] = useState(false);
  const [jobForm, setJobForm] = useState<JobFormState | null>(null);
  const [savingJob, setSavingJob] = useState(false);

  const [showResumeModal, setShowResumeModal] = useState(false);
  const [resumeLinkUrl, setResumeLinkUrl] = useState('');
  const [resumeFile, setResumeFile] = useState<File | null>(null);
  const [savingResume, setSavingResume] = useState(false);

  const [showLetterModal, setShowLetterModal] = useState(false);
  const [coverLetterHtml, setCoverLetterHtml] = useState('');
  const [notes, setNotes] = useState('');
  const [actionDate, setActionDate] = useState('');
  const [savingLetter, setSavingLetter] = useState(false);

  const [showInterviewModal, setShowInterviewModal] = useState(false);
  const [editingInterview, setEditingInterview] = useState<Interview | null>(null);
  const [interviewForm, setInterviewForm] = useState<InterviewFormState>(emptyInterviewForm);
  const [savingInterview, setSavingInterview] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.get<JobDetail>(`/api/jobs/${jobId}`);
      setJob(data);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to load the job.');
    } finally {
      setLoading(false);
    }
  }, [jobId]);

  useEffect(() => {
    void load();
  }, [load]);

  if (loading) {
    return (
      <div className="page">
        <div className="spinner" aria-label="Loading" />
      </div>
    );
  }

  if (!job) {
    return (
      <div className="page">
        <ErrorBanner message={error} />
        <Link to="/" className="btn btn-ghost">
          &larr; Back to job searches
        </Link>
      </div>
    );
  }

  const application = job.application;

  async function handleJobEditSubmit(e: FormEvent) {
    e.preventDefault();
    if (!jobForm) {
      return;
    }
    setSavingJob(true);
    setError(null);
    try {
      const body: Record<string, unknown> = {
        title: jobForm.title.trim(),
        company: jobForm.company.trim(),
        status: jobForm.status,
      };
      if (jobForm.descriptionHtml.trim()) {
        body.descriptionHtml = jobForm.descriptionHtml;
      }
      if (jobForm.descriptionUrl.trim()) {
        body.descriptionUrl = jobForm.descriptionUrl;
      }
      await api.patch(`/api/jobs/${jobId}`, body);
      setShowEditJob(false);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to update the job.');
    } finally {
      setSavingJob(false);
    }
  }

  async function handleJobDelete() {
    if (!window.confirm('Delete this job and its application?')) {
      return;
    }
    setError(null);
    try {
      await api.delete(`/api/jobs/${jobId}`);
      window.history.back();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to delete the job.');
    }
  }

  async function handleCreateApplication() {
    setError(null);
    try {
      await api.post<Application>(`/api/jobs/${jobId}/application`, {});
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to create the application.');
    }
  }

  async function handleResumeSave(e: FormEvent) {
    e.preventDefault();
    if (!application) {
      return;
    }
    setSavingResume(true);
    setError(null);
    try {
      if (resumeFile) {
        const fd = new FormData();
        fd.append('file', resumeFile);
        await api.postForm<Application>(`/api/applications/${application.id}/resume-file`, fd);
      } else if (resumeLinkUrl.trim()) {
        await api.patch<Application>(`/api/applications/${application.id}`, {
          resumeKind: 'link',
          resumeLinkUrl: resumeLinkUrl.trim(),
        });
      }
      setShowResumeModal(false);
      setResumeFile(null);
      setResumeLinkUrl('');
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to save the resume.');
    } finally {
      setSavingResume(false);
    }
  }

  async function handleClearResume() {
    if (!application || !window.confirm('Remove the resume from this application?')) {
      return;
    }
    setError(null);
    try {
      await api.patch<Application>(`/api/applications/${application.id}`, { resumeKind: null });
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to clear the resume.');
    }
  }

  async function handleLetterSave(e: FormEvent) {
    e.preventDefault();
    if (!application) {
      return;
    }
    setSavingLetter(true);
    setError(null);
    try {
      await api.patch<Application>(`/api/applications/${application.id}`, {
        coverLetterHtml: coverLetterHtml.trim() ? coverLetterHtml : null,
        notes: notes.trim() ? notes : null,
        actionDate: actionDate || null,
      });
      setShowLetterModal(false);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to save.');
    } finally {
      setSavingLetter(false);
    }
  }

  async function handleApplicationDelete() {
    if (!application || !window.confirm('Delete this application?')) {
      return;
    }
    setError(null);
    try {
      await api.delete(`/api/applications/${application.id}`);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to delete the application.');
    }
  }

  async function handleInterviewSave(e: FormEvent) {
    e.preventDefault();
    if (!application || !interviewForm.date) {
      return;
    }
    setSavingInterview(true);
    setError(null);
    try {
      const body = {
        date: interviewForm.date,
        interviewers: interviewForm.interviewers
          .split(',')
          .map((s) => s.trim())
          .filter(Boolean),
        notes: interviewForm.notes.trim() ? interviewForm.notes : null,
      };
      if (editingInterview) {
        await api.patch(`/api/interviews/${editingInterview.id}`, body);
      } else {
        await api.post(`/api/applications/${application.id}/interviews`, body);
      }
      setShowInterviewModal(false);
      setEditingInterview(null);
      setInterviewForm(emptyInterviewForm);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to save the interview.');
    } finally {
      setSavingInterview(false);
    }
  }

  async function handleInterviewDelete(interview: Interview) {
    if (!window.confirm('Delete this interview?')) {
      return;
    }
    setError(null);
    try {
      await api.delete(`/api/interviews/${interview.id}`);
      await load();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to delete the interview.');
    }
  }

  function openInterviewModal(interview: Interview | null) {
    setEditingInterview(interview);
    setInterviewForm(
      interview
        ? {
            date: toDateTimeInputValue(interview.date),
            interviewers: interview.interviewers.join(', '),
            notes: interview.notes ?? '',
          }
        : emptyInterviewForm
    );
    setShowInterviewModal(true);
  }

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <Link to={`/searches/${job.jobSearchId}`} className="back-link">
            &larr; Back to job search
          </Link>
          <h1>{job.title}</h1>
          <p className="page-subtitle">
            {job.company} &middot; <span className={`badge badge-${job.status}`}>{JOB_STATUS_LABELS[job.status]}</span>{' '}
            &middot; added {formatDate(job.createdAt)}
          </p>
        </div>
        <div className="btn-group">
          <button
            type="button"
            className="btn btn-ghost"
            onClick={() => {
              setJobForm({
                title: job.title,
                company: job.company,
                status: job.status,
                descriptionHtml: job.descriptionHtml ?? '',
                descriptionUrl: job.descriptionUrl ?? '',
              });
              setShowEditJob(true);
            }}
          >
            Edit
          </button>
          <button type="button" className="btn btn-danger-ghost" onClick={() => void handleJobDelete()}>
            Delete
          </button>
        </div>
      </div>

      <ErrorBanner message={error} />

      {job.descriptionHtml ? (
        <section className="panel">
          <h2>Description</h2>
          <div className="rich-text" dangerouslySetInnerHTML={{ __html: job.descriptionHtml }} />
        </section>
      ) : null}
      {job.descriptionUrl ? (
        <p>
          <a href={job.descriptionUrl} target="_blank" rel="noreferrer">
            View original posting
          </a>
        </p>
      ) : null}

      <section className="panel">
        <div className="panel-header">
          <h2>Application</h2>
          {application && (
            <div className="btn-group">
              <button type="button" className="btn btn-sm btn-ghost" onClick={() => setShowResumeModal(true)}>
                Resume
              </button>
              <button
                type="button"
                className="btn btn-sm btn-ghost"
                onClick={() => {
                  setCoverLetterHtml(application.coverLetterHtml ?? '');
                  setNotes(application.notes ?? '');
                  setActionDate(application.actionDate ?? '');
                  setShowLetterModal(true);
                }}
              >
                Cover letter / notes
              </button>
              <button type="button" className="btn btn-sm btn-danger-ghost" onClick={() => void handleApplicationDelete()}>
                Delete
              </button>
            </div>
          )}
        </div>

        {!application ? (
          <div className="empty-state">
            <p>No application for this job yet.</p>
            <button type="button" className="btn btn-primary" onClick={() => void handleCreateApplication()}>
              Create application
            </button>
          </div>
        ) : (
          <div className="application-detail">
            {application.actionDate && (
              <div className="detail-row">
                <span className="detail-label">Action date</span>
                <span>{formatDate(application.actionDate)}</span>
              </div>
            )}
            <div className="detail-row">
              <span className="detail-label">Resume</span>
              <span>
                {application.resumeKind === 'file' ? (
                  <>
                    <a href={`/api/applications/${application.id}/resume/download`} target="_blank" rel="noreferrer">
                      {application.resumeFileName}
                    </a>{' '}
                    <small>
                      ({formatFileSize(application.resumeFileSize)}, {application.resumeMimeType})
                    </small>
                  </>
                ) : application.resumeKind === 'link' ? (
                  <a href={application.resumeLinkUrl ?? '#'} target="_blank" rel="noreferrer">
                    {application.resumeLinkUrl}
                  </a>
                ) : (
                  <em>None</em>
                )}
                {application.resumeKind && (
                  <>
                    {' '}
                    <button type="button" className="btn btn-sm btn-ghost" onClick={() => void handleClearResume()}>
                      Clear
                    </button>
                  </>
                )}
              </span>
            </div>
            {application.coverLetterHtml && (
              <div className="detail-row">
                <span className="detail-label">Cover letter</span>
                <div className="cover-letter-preview" dangerouslySetInnerHTML={{ __html: application.coverLetterHtml }} />
              </div>
            )}
            {application.notes && (
              <div className="detail-row">
                <span className="detail-label">Notes</span>
                <span className="pre-wrap">{application.notes}</span>
              </div>
            )}
          </div>
        )}
      </section>

      <section className="panel">
        <div className="panel-header">
          <h2>Interviews</h2>
          {application && (
            <button type="button" className="btn btn-sm btn-primary" onClick={() => openInterviewModal(null)}>
              Add interview
            </button>
          )}
        </div>

        {!application ? (
          <p className="muted">Create an application first.</p>
        ) : application.interviews.length === 0 ? (
          <p className="muted">No interviews recorded.</p>
        ) : (
          <ul className="card-list">
            {application.interviews.map((interview) => (
              <li key={interview.id} className="card">
                <div className="card-title-row">
                  <span className="card-title">{formatDateTime(interview.date)}</span>
                  <div className="btn-group">
                    <button type="button" className="btn btn-sm btn-ghost" onClick={() => openInterviewModal(interview)}>
                      Edit
                    </button>
                    <button
                      type="button"
                      className="btn btn-sm btn-danger-ghost"
                      onClick={() => void handleInterviewDelete(interview)}
                    >
                      Delete
                    </button>
                  </div>
                </div>
                {interview.interviewers.length > 0 && (
                  <div className="card-meta">With {interview.interviewers.join(', ')}</div>
                )}
                {interview.notes && <p className="pre-wrap">{interview.notes}</p>}
              </li>
            ))}
          </ul>
        )}
      </section>

      {showEditJob && jobForm && (
        <Modal title="Edit job" onClose={() => setShowEditJob(false)}>
          <form onSubmit={handleJobEditSubmit} className="form">
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
                onChange={(e) => setJobForm({ ...jobForm, status: e.target.value as JobStatus })}
              >
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
              <button type="button" className="btn btn-ghost" onClick={() => setShowEditJob(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={savingJob}>
                {savingJob ? 'Saving…' : 'Save'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {showResumeModal && application && (
        <Modal title="Set resume" onClose={() => setShowResumeModal(false)}>
          <form onSubmit={handleResumeSave} className="form">
            <label className="field">
              <span>Upload a file</span>
              <input type="file" onChange={(e) => setResumeFile(e.target.files?.[0] ?? null)} />
            </label>
            <div className="divider">or</div>
            <label className="field">
              <span>Use a link</span>
              <input
                type="url"
                placeholder="https://example.com/my-resume.pdf"
                value={resumeLinkUrl}
                onChange={(e) => setResumeLinkUrl(e.target.value)}
              />
            </label>
            <div className="form-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowResumeModal(false)}>
                Cancel
              </button>
              <button
                type="submit"
                className="btn btn-primary"
                disabled={savingResume || (!resumeFile && !resumeLinkUrl.trim())}
              >
                {savingResume ? 'Saving…' : 'Save resume'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {showLetterModal && application && (
        <Modal title="Cover letter & notes" onClose={() => setShowLetterModal(false)}>
          <form onSubmit={handleLetterSave} className="form">
            <label className="field">
              <span>Action date (date you applied)</span>
              <input
                type="date"
                value={actionDate}
                onChange={(e) => setActionDate(e.target.value)}
              />
            </label>
            <label className="field">
              <span>Cover letter</span>
              <RichTextEditor
                content={coverLetterHtml}
                onChange={setCoverLetterHtml}
                placeholder="Write or paste your cover letter here..."
              />
            </label>
            <label className="field">
              <span>Notes (optional)</span>
              <textarea rows={4} value={notes} onChange={(e) => setNotes(e.target.value)} />
            </label>
            <div className="form-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowLetterModal(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={savingLetter}>
                {savingLetter ? 'Saving…' : 'Save'}
              </button>
            </div>
          </form>
        </Modal>
      )}

      {showInterviewModal && application && (
        <Modal title={editingInterview ? 'Edit interview' : 'Add interview'} onClose={() => setShowInterviewModal(false)}>
          <form onSubmit={handleInterviewSave} className="form">
            <label className="field">
              <span>Date and time</span>
              <input
                type="datetime-local"
                value={interviewForm.date}
                onChange={(e) => setInterviewForm({ ...interviewForm, date: e.target.value })}
                required
                autoFocus
              />
            </label>
            <label className="field">
              <span>Interviewers (comma separated)</span>
              <input
                value={interviewForm.interviewers}
                onChange={(e) => setInterviewForm({ ...interviewForm, interviewers: e.target.value })}
              />
            </label>
            <label className="field">
              <span>Notes (optional)</span>
              <textarea
                rows={4}
                value={interviewForm.notes}
                onChange={(e) => setInterviewForm({ ...interviewForm, notes: e.target.value })}
              />
            </label>
            <div className="form-actions">
              <button type="button" className="btn btn-ghost" onClick={() => setShowInterviewModal(false)}>
                Cancel
              </button>
              <button type="submit" className="btn btn-primary" disabled={savingInterview}>
                {savingInterview ? 'Saving…' : 'Save'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}
