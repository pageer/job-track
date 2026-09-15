import type { JobStatus } from './types';
import { toDateTimeInputValue } from './utils';

export interface ExtractedInterview {
  date: string | null;
  interviewers: string[];
  notes: string | null;
}

export interface ExtractedJob {
  title: string | null;
  company: string | null;
  status: JobStatus;
  descriptionUrl: string | null;
  descriptionHtml: string | null;
}

export interface ExtractResponse {
  intent: 'job' | 'interview';
  job: ExtractedJob | null;
  interview: ExtractedInterview | null;
  summary: string | null;
}

export interface ReviewInterview {
  date: string;
  interviewers: string;
  notes: string;
}

export function toReviewInterview(
  interview: ExtractedInterview | null,
): ReviewInterview {
  return {
    date: toDateTimeInputValue(interview?.date ?? null),
    interviewers: (interview?.interviewers ?? []).join(', '),
    notes: interview?.notes ?? '',
  };
}

export function splitInterviewers(value: string): string[] {
  return value
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean);
}

export function toInterviewPayload(interview: ReviewInterview): {
  date: string;
  interviewers: string[];
  notes: string | null;
} {
  return {
    date: interview.date,
    interviewers: splitInterviewers(interview.interviewers),
    notes: interview.notes.trim() ? interview.notes.trim() : null,
  };
}
