import { afterEach, describe, expect, it } from 'vitest';
import { JOB_STATUSES } from './types';
import {
  DEFAULT_ACTIVE_STATUSES,
  defaultJobFilters,
  loadJobFilters,
  parseJobFilters,
  saveJobFilters,
} from './jobFilters';

afterEach(() => {
  sessionStorage.clear();
});

describe('DEFAULT_ACTIVE_STATUSES', () => {
  it('is every status except rejected', () => {
    expect([...DEFAULT_ACTIVE_STATUSES]).toEqual(
      JOB_STATUSES.filter((s) => s !== 'rejected'),
    );
  });
});

describe('parseJobFilters', () => {
  it('returns the defaults for null', () => {
    expect(parseJobFilters(null)).toEqual(defaultJobFilters());
  });

  it('returns the defaults for invalid JSON', () => {
    expect(parseJobFilters('not json')).toEqual(defaultJobFilters());
  });

  it('returns the defaults for non-object JSON', () => {
    expect(parseJobFilters('"hi"')).toEqual(defaultJobFilters());
    expect(parseJobFilters('5')).toEqual(defaultJobFilters());
  });

  it('parses a stored payload', () => {
    const parsed = parseJobFilters(
      JSON.stringify({
        statuses: ['rejected', 'accepted'],
        filter: 'acme',
        sortBy: 'actionDate',
      }),
    );
    expect([...parsed.statuses]).toEqual(['rejected', 'accepted']);
    expect(parsed.filter).toBe('acme');
    expect(parsed.sortBy).toBe('actionDate');
  });

  it('keeps an explicitly empty status set (user deselected everything)', () => {
    const parsed = parseJobFilters(
      JSON.stringify({ statuses: [], filter: '', sortBy: 'createdAt' }),
    );
    expect(parsed.statuses.size).toBe(0);
  });

  it('drops unknown statuses but keeps valid ones', () => {
    const parsed = parseJobFilters(
      JSON.stringify({
        statuses: ['rejected', 'nope'],
        filter: '',
        sortBy: 'createdAt',
      }),
    );
    expect([...parsed.statuses]).toEqual(['rejected']);
  });

  it('falls back per field for invalid values', () => {
    const parsed = parseJobFilters(
      JSON.stringify({ statuses: 'all', filter: 5, sortBy: 'nope' }),
    );
    expect(parsed).toEqual(defaultJobFilters());
  });
});

describe('saveJobFilters / loadJobFilters', () => {
  it('round-trips the filters for a search', () => {
    saveJobFilters('7', {
      statuses: new Set(['rejected']),
      filter: 'acme',
      sortBy: 'actionDate',
    });
    const loaded = loadJobFilters('7');
    expect([...loaded.statuses]).toEqual(['rejected']);
    expect(loaded.filter).toBe('acme');
    expect(loaded.sortBy).toBe('actionDate');
  });

  it('keeps searches independent', () => {
    saveJobFilters('7', {
      statuses: new Set(['rejected']),
      filter: 'acme',
      sortBy: 'actionDate',
    });
    expect(loadJobFilters('8')).toEqual(defaultJobFilters());
  });

  it('loads the defaults when nothing is stored', () => {
    expect(loadJobFilters('7')).toEqual(defaultJobFilters());
  });
});
