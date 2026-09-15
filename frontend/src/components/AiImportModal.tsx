import { useState, type FormEvent } from 'react';
import { api, ApiError } from '../api';
import { JOB_STATUS_LABELS, type Application, type JobStatus } from '../types';
import {
  toInterviewPayload,
  toReviewInterview,
  type ExtractResponse,
  type ReviewInterview,
} from '../aiImport';
import ErrorBanner from './ErrorBanner';
import Modal from './Modal';

interface AiImportModalProps {
  intent: 'job' | 'interview';
  jobSearchId?: string;
  jobId?: string;
  applicationId?: number | null;
  onClose: () => void;
  onSaved: () => void;
}

interface JobFormState {
  title: string;
  company: string;
  status: JobStatus | '';
  descriptionUrl: string;
  descriptionHtml: string;
}

const emptyJobForm: JobFormState = {
  title: '',
  company: '',
  status: '',
  descriptionUrl: '',
  descriptionHtml: '',
};

const emptyInterview: ReviewInterview = {
  date: '',
  interviewers: '',
  notes: '',
};

export default function AiImportModal({
  intent,
  jobSearchId,
  jobId,
  applicationId = null,
  onClose,
  onSaved,
}: AiImportModalProps) {
  const [phase, setPhase] = useState<'paste' | 'review'>('paste');
  const [text, setText] = useState('');
  const [extracting, setExtracting] = useState(false);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [jobForm, setJobForm] = useState<JobFormState>(emptyJobForm);
  const [interviewEnabled, setInterviewEnabled] = useState(false);
  const [interview, setInterview] = useState<ReviewInterview>(emptyInterview);
  const [summary, setSummary] = useState<string | null>(null);

  const title =
    intent === 'job' ? 'Add job from message' : 'Add interview from message';

  async function handleExtract(e: FormEvent) {
    e.preventDefault();
    if (!text.trim()) {
      return;
    }
    setExtracting(true);
    setError(null);
    try {
      const result = await api.post<ExtractResponse>('/api/ai/extract', {
        text: text.trim(),
        intent,
      });
      const job = result.job;
      setJobForm({
        title: job?.title ?? '',
        company: job?.company ?? '',
        status: job?.status ?? 'investigating',
        descriptionUrl: job?.descriptionUrl ?? '',
        descriptionHtml: job?.descriptionHtml ?? '',
      });
      const review = toReviewInterview(result.interview);
      setInterview(review);
      setSummary(result.summary);
      setInterviewEnabled(
        intent === 'interview' ||
          review.date !== '' ||
          review.interviewers.trim() !== '' ||
          review.notes.trim() !== '',
      );
      setPhase('review');
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : 'Failed to extract data from the message.',
      );
    } finally {
      setExtracting(false);
    }
  }

  async function saveInterview() {
    if (!jobId) {
      return;
    }
    let appId = applicationId;
    if (appId === null) {
      const app = await api.post<Application>(
        `/api/jobs/${jobId}/application`,
        {},
      );
      appId = app.id;
    }
    await api.post(
      `/api/applications/${appId}/interviews`,
      toInterviewPayload(interview),
    );
  }

  async function saveJobWithOptionalInterview() {
    if (!jobSearchId) {
      return;
    }
    const body: Record<string, unknown> = {
      title: jobForm.title.trim(),
      company: jobForm.company.trim(),
    };
    if (jobForm.status) {
      body.status = jobForm.status;
    }
    if (jobForm.descriptionUrl.trim()) {
      body.descriptionUrl = jobForm.descriptionUrl;
    }
    if (jobForm.descriptionHtml.trim()) {
      body.descriptionHtml = jobForm.descriptionHtml;
    }
    const createdJob = await api.post<{ id: number }>(
      `/api/job-searches/${jobSearchId}/jobs`,
      body,
    );
    if (interviewEnabled && interview.date.trim()) {
      const app = await api.post<Application>(
        `/api/jobs/${createdJob.id}/application`,
        {},
      );
      await api.post(
        `/api/applications/${app.id}/interviews`,
        toInterviewPayload(interview),
      );
    }
  }

  async function handleSave(e: FormEvent) {
    e.preventDefault();
    if (
      (intent === 'job' &&
        (!jobForm.title.trim() || !jobForm.company.trim())) ||
      (interviewEnabled && !interview.date.trim())
    ) {
      if (interviewEnabled && !interview.date.trim()) {
        setError('Interview date and time is required.');
      }
      return;
    }
    setSaving(true);
    setError(null);
    try {
      if (intent === 'interview') {
        await saveInterview();
      } else {
        await saveJobWithOptionalInterview();
      }
      onSaved();
      onClose();
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Failed to save.');
    } finally {
      setSaving(false);
    }
  }

  return (
    <Modal title={title} onClose={onClose}>
      {phase === 'paste' ? (
        <form onSubmit={handleExtract} className="form">
          <p className="muted">
            {intent === 'job'
              ? 'Paste a recruiter email or LinkedIn message. The job details (and any scheduled call) will be extracted for you to review before saving.'
              : 'Paste the email or LinkedIn message confirming the interview. The schedule details will be extracted for you to review before saving.'}
          </p>
          <label className="field">
            <span>Message</span>
            <textarea
              rows={10}
              value={text}
              onChange={(e) => setText(e.target.value)}
              placeholder="Paste the email or LinkedIn message here…"
              required
              autoFocus
            />
          </label>
          <ErrorBanner message={error} />
          <div className="form-actions">
            <button type="button" className="btn btn-ghost" onClick={onClose}>
              Cancel
            </button>
            <button
              type="submit"
              className="btn btn-primary"
              disabled={extracting || !text.trim()}
            >
              {extracting ? 'Extracting…' : 'Extract data'}
            </button>
          </div>
        </form>
      ) : (
        <form onSubmit={handleSave} className="form">
          {summary && <p className="muted">{summary}</p>}
          {intent === 'job' && (
            <>
              <label className="field">
                <span>Title</span>
                <input
                  value={jobForm.title}
                  onChange={(e) =>
                    setJobForm({ ...jobForm, title: e.target.value })
                  }
                  required
                  autoFocus
                />
              </label>
              <label className="field">
                <span>Company</span>
                <input
                  value={jobForm.company}
                  onChange={(e) =>
                    setJobForm({ ...jobForm, company: e.target.value })
                  }
                  required
                />
              </label>
              <label className="field">
                <span>Status</span>
                <select
                  value={jobForm.status}
                  onChange={(e) =>
                    setJobForm({
                      ...jobForm,
                      status: e.target.value as JobStatus | '',
                    })
                  }
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
                  onChange={(e) =>
                    setJobForm({
                      ...jobForm,
                      descriptionUrl: e.target.value,
                    })
                  }
                />
              </label>
              <label className="field">
                <span>Description (optional)</span>
                <textarea
                  rows={5}
                  value={jobForm.descriptionHtml}
                  onChange={(e) =>
                    setJobForm({ ...jobForm, descriptionHtml: e.target.value })
                  }
                />
              </label>
              <label className="check-field">
                <input
                  type="checkbox"
                  checked={interviewEnabled}
                  onChange={(e) => setInterviewEnabled(e.target.checked)}
                />
                <span>
                  Also record an interview
                  <small>
                    This also creates an application and moves the job to
                    &ldquo;Applied&rdquo;.
                  </small>
                </span>
              </label>
            </>
          )}
          {interviewEnabled && (
            <>
              <label className="field">
                <span>Interview date and time</span>
                <input
                  type="datetime-local"
                  value={interview.date}
                  onChange={(e) =>
                    setInterview({ ...interview, date: e.target.value })
                  }
                />
              </label>
              <label className="field">
                <span>Interviewers (comma separated)</span>
                <input
                  value={interview.interviewers}
                  onChange={(e) =>
                    setInterview({ ...interview, interviewers: e.target.value })
                  }
                />
              </label>
              <label className="field">
                <span>Notes (optional)</span>
                <textarea
                  rows={3}
                  value={interview.notes}
                  onChange={(e) =>
                    setInterview({ ...interview, notes: e.target.value })
                  }
                />
              </label>
            </>
          )}
          <ErrorBanner message={error} />
          <div className="form-actions">
            <button
              type="button"
              className="btn btn-ghost"
              onClick={() => setPhase('paste')}
            >
              Back
            </button>
            <button type="button" className="btn btn-ghost" onClick={onClose}>
              Cancel
            </button>
            <button type="submit" className="btn btn-primary" disabled={saving}>
              {saving ? 'Saving…' : 'Save'}
            </button>
          </div>
        </form>
      )}
    </Modal>
  );
}
