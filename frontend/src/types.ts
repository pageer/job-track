export type JobStatus = 'investigating' | 'applied' | 'in_progress' | 'rejected' | 'accepted';
export type ResumeKind = 'file' | 'link';

export interface User {
  id: number;
  email: string;
  name: string;
  roles: string[];
  createdAt: string;
}

export interface JobSearch {
  id: number;
  name: string;
  startDate: string;
  endDate: string | null;
  createdAt: string;
  jobCount: number;
}

export interface JobSearchDetail extends JobSearch {
  jobs: JobSummary[];
}

export interface JobSummary {
  id: number;
  title: string;
  company: string;
  status: JobStatus;
  jobSearchId: number;
  createdAt: string;
}

export interface Interview {
  id: number;
  date: string;
  interviewers: string[];
  notes: string | null;
  createdAt: string;
}

export interface Application {
  id: number;
  resumeKind: ResumeKind | null;
  resumeFileName: string | null;
  resumeMimeType: string | null;
  resumeFileSize: number | null;
  resumeLinkUrl: string | null;
  coverLetterHtml: string | null;
  notes: string | null;
  actionDate: string | null;
  createdAt: string;
  jobId: number;
  jobTitle: string | null;
  jobCompany: string | null;
  interviews: Interview[];
}

export interface JobDetail extends JobSummary {
  descriptionHtml: string | null;
  descriptionUrl: string | null;
  application: Application | null;
}

export interface Resume {
  id: number;
  name: string;
  kind: ResumeKind;
  fileName: string | null;
  mimeType: string | null;
  fileSize: number | null;
  linkUrl: string | null;
  createdAt: string;
}

export interface CoverLetter {
  id: number;
  name: string;
  body: string;
  createdAt: string;
}

export const JOB_STATUSES: JobStatus[] = [
  'investigating',
  'applied',
  'in_progress',
  'rejected',
  'accepted',
];

export const JOB_STATUS_LABELS: Record<JobStatus, string> = {
  investigating: 'Investigating',
  applied: 'Applied',
  in_progress: 'In progress',
  rejected: 'Rejected',
  accepted: 'Accepted',
};
