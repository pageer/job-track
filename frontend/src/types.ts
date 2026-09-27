export type JobStatus =
  | 'investigating'
  | 'applied'
  | 'in_progress'
  | 'no_response'
  | 'rejected'
  | 'accepted';
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

export interface JobActivity {
  id: number;
  description: string;
  details: string | null;
  date: string;
  hoursSpent: number;
  createdAt: string;
}

export interface Todo {
  id: number;
  description: string;
  details: string | null;
  targetDate: string;
  createdAt: string;
}

export interface JobSearchDetail extends JobSearch {
  jobs: JobSummary[];
}

export interface JobSummary {
  id: number;
  title: string;
  company: string;
  companyId: number | null;
  status: JobStatus;
  jobSearchId: number;
  actionDate: string | null;
  createdAt: string;
}

export interface JobNote {
  id: number;
  content: string;
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
  notes: JobNote[];
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

export interface OneDriveFile {
  id: string;
  name: string;
  size: number | null;
  lastModifiedDateTime: string | null;
  webUrl: string;
  mimeType: string | null;
}

export interface OneDriveStatus {
  clientConfigured: boolean;
  connected: boolean;
  accountEmail: string | null;
  accountDisplayName: string | null;
  folderPath: string;
}

export interface CoverLetter {
  id: number;
  name: string;
  body: string;
  createdAt: string;
}

export interface Company {
  id: number;
  name: string;
  jobCount: number;
  createdAt: string;
}

export interface Contact {
  id: number;
  date: string;
  description: string | null;
  needsFollowUp: boolean;
  personId: number;
  createdAt: string;
}

export interface Person {
  id: number;
  name: string;
  email: string | null;
  linkedInUrl: string | null;
  notes: string | null;
  companyId: number | null;
  companyName: string | null;
  createdAt: string;
  lastContact: Contact | null;
  needsFollowUp: boolean;
}

export interface PersonDetail extends Person {
  contacts: Contact[];
}

export const JOB_STATUSES: JobStatus[] = [
  'investigating',
  'applied',
  'in_progress',
  'no_response',
  'rejected',
  'accepted',
];

export const JOB_STATUS_LABELS: Record<JobStatus, string> = {
  investigating: 'Investigating',
  applied: 'Applied',
  in_progress: 'In progress',
  no_response: 'No response',
  rejected: 'Rejected',
  accepted: 'Accepted',
};
