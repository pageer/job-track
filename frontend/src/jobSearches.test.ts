import { describe, expect, it } from 'vitest';
import type { JobSearch } from './types';
import { activeJobSearch, jobsNavTarget } from './jobSearches';

function search(overrides: Partial<JobSearch>): JobSearch {
  return {
    id: 1,
    name: 'Summer 2026',
    startDate: '2026-06-01',
    endDate: null,
    createdAt: '2026-06-01T00:00:00Z',
    jobCount: 3,
    ...overrides,
  };
}

const closed = (id: number) =>
  search({ id, name: `Closed ${id}`, endDate: '2026-07-01' });

describe('activeJobSearch', () => {
  it('returns the only ongoing search', () => {
    const ongoing = search({ id: 7 });
    expect(activeJobSearch([closed(1), ongoing, closed(2)])).toBe(ongoing);
  });

  it('returns null when nothing is ongoing', () => {
    expect(activeJobSearch([closed(1), closed(2)])).toBeNull();
  });

  it('returns null when several searches are ongoing', () => {
    expect(activeJobSearch([search({ id: 1 }), search({ id: 2 })])).toBeNull();
  });

  it('returns null for an empty list', () => {
    expect(activeJobSearch([])).toBeNull();
  });
});

describe('jobsNavTarget', () => {
  it('links to the job list of a single active search', () => {
    expect(jobsNavTarget([closed(1), search({ id: 7 })])).toEqual({
      to: '/searches/7',
      label: 'Jobs',
      end: false,
    });
  });

  it('links to the job search list when no search is active', () => {
    expect(jobsNavTarget([closed(1), closed(2)])).toEqual({
      to: '/',
      label: 'Job Searches',
      end: true,
    });
  });

  it('links to the job search list when several searches are active', () => {
    const target = jobsNavTarget([search({ id: 1 }), search({ id: 2 })]);
    expect(target).toEqual({ to: '/', label: 'Job Searches', end: true });
  });

  it('falls back to the job search list before the searches load', () => {
    expect(jobsNavTarget([])).toEqual({
      to: '/',
      label: 'Job Searches',
      end: true,
    });
  });
});
