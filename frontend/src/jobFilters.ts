import type { JobStatus } from './types';
import { JOB_STATUSES } from './types';

export type SortKey = 'createdAt' | 'actionDate';

export interface JobFilters {
  statuses: Set<JobStatus>;
  filter: string;
  sortBy: SortKey;
}

/** All statuses except rejected — the state of the filters before the user touches them. */
export const DEFAULT_ACTIVE_STATUSES: Set<JobStatus> = new Set(
  JOB_STATUSES.filter((s) => s !== 'rejected'),
);

export function defaultJobFilters(): JobFilters {
  return {
    statuses: new Set(DEFAULT_ACTIVE_STATUSES),
    filter: '',
    sortBy: 'createdAt',
  };
}

interface StoredJobFilters {
  statuses: JobStatus[];
  filter: string;
  sortBy: SortKey;
}

function storageKey(searchId: string): string {
  return `jobFilters:${searchId}`;
}

/**
 * Parses a stored JSON payload into filters, falling back to the defaults
 * field by field so a partially valid payload keeps its valid parts.
 */
export function parseJobFilters(raw: string | null): JobFilters {
  const filters = defaultJobFilters();
  if (raw === null) {
    return filters;
  }
  let data: unknown;
  try {
    data = JSON.parse(raw);
  } catch {
    return filters;
  }
  if (typeof data !== 'object' || data === null) {
    return filters;
  }
  const { statuses, filter, sortBy } = data as Record<string, unknown>;
  if (Array.isArray(statuses)) {
    filters.statuses = new Set(
      statuses.filter((s): s is JobStatus =>
        JOB_STATUSES.includes(s as JobStatus),
      ),
    );
  }
  if (typeof filter === 'string') {
    filters.filter = filter;
  }
  if (sortBy === 'createdAt' || sortBy === 'actionDate') {
    filters.sortBy = sortBy;
  }
  return filters;
}

/** Filters previously saved for this job search, or the defaults. */
export function loadJobFilters(searchId: string): JobFilters {
  return parseJobFilters(sessionStorage.getItem(storageKey(searchId)));
}

/** Persists the filters for a job search in sessionStorage. */
export function saveJobFilters(searchId: string, filters: JobFilters): void {
  const stored: StoredJobFilters = {
    statuses: [...filters.statuses],
    filter: filters.filter,
    sortBy: filters.sortBy,
  };
  sessionStorage.setItem(storageKey(searchId), JSON.stringify(stored));
}
