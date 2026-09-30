import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useState,
  type ReactNode,
} from 'react';
import { api } from './api';
import type { JobSearch } from './types';

/** A job search counts as active while it has no end date. */
export function activeJobSearch(searches: JobSearch[]): JobSearch | null {
  const active = searches.filter((search) => search.endDate === null);
  return active.length === 1 ? active[0] : null;
}

export interface JobsNavTarget {
  to: string;
  label: string;
  end: boolean;
}

/**
 * With exactly one active search the nav link goes straight to that search's
 * job list; with none or several it goes to the job search list instead.
 */
export function jobsNavTarget(searches: JobSearch[]): JobsNavTarget {
  const active = activeJobSearch(searches);
  return active
    ? { to: `/searches/${active.id}`, label: 'Jobs', end: false }
    : { to: '/', label: 'Job Searches', end: true };
}

interface JobSearchesContextValue {
  searches: JobSearch[];
  refresh: () => Promise<void>;
}

const JobSearchesContext = createContext<JobSearchesContextValue | null>(null);

export function JobSearchesProvider({ children }: { children: ReactNode }) {
  const [searches, setSearches] = useState<JobSearch[]>([]);

  const refresh = useCallback(async () => {
    try {
      setSearches(await api.get<JobSearch[]>('/api/job-searches'));
    } catch {
      // Keep the last known list; the nav falls back to the job search list.
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  return (
    <JobSearchesContext.Provider value={{ searches, refresh }}>
      {children}
    </JobSearchesContext.Provider>
  );
}

export function useJobSearches(): JobSearchesContextValue {
  const ctx = useContext(JobSearchesContext);
  if (!ctx) {
    throw new Error('useJobSearches must be used within a JobSearchesProvider');
  }
  return ctx;
}
