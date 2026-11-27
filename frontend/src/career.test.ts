import { describe, expect, it } from 'vitest';
import {
  firstLine,
  formatYearMonth,
  hasMoreThanFirstLine,
  sortAccomplishmentsDescending,
} from './career';
import type { Accomplishment } from './types';

function accomplishment(
  id: number,
  date: string,
  overrides: Partial<Accomplishment> = {},
): Accomplishment {
  return {
    id,
    date,
    company: 'Acme Inc',
    description: `Entry ${id}`,
    ...overrides,
  };
}

describe('formatYearMonth', () => {
  it('shows year and month only', () => {
    expect(formatYearMonth('2026-03-15')).toBe('2026-03');
    expect(formatYearMonth('2026-03-15T00:00:00+00:00')).toBe('2026-03');
    expect(formatYearMonth('2026-03-01T00:00:00.000Z')).toBe('2026-03');
  });
});

describe('firstLine', () => {
  it('returns the whole text when there is no line break', () => {
    expect(firstLine('one line')).toBe('one line');
    expect(firstLine('')).toBe('');
  });

  it('returns text up to the first line break (LF, CRLF, or CR)', () => {
    expect(firstLine('first\nsecond')).toBe('first');
    expect(firstLine('first\r\nsecond')).toBe('first');
    expect(firstLine('first\rsecond')).toBe('first');
    expect(firstLine('first\nsecond\nthird')).toBe('first');
  });

  it('handles a leading line break', () => {
    expect(firstLine('\nsecond')).toBe('');
  });
});

describe('hasMoreThanFirstLine', () => {
  it('is true when there is content beyond the first line', () => {
    expect(hasMoreThanFirstLine('first\nsecond')).toBe(true);
    expect(hasMoreThanFirstLine('first\n')).toBe(true);
  });

  it('is false for single-line text', () => {
    expect(hasMoreThanFirstLine('one line')).toBe(false);
    expect(hasMoreThanFirstLine('')).toBe(false);
  });
});

describe('sortAccomplishmentsDescending', () => {
  it('sorts newest first (reverse chronological)', () => {
    const sorted = sortAccomplishmentsDescending([
      accomplishment(1, '2024-01-01'),
      accomplishment(2, '2026-03-01'),
      accomplishment(3, '2025-06-15'),
    ]);

    expect(sorted.map((a) => a.id)).toEqual([2, 3, 1]);
  });

  it('orders by calendar day even with ISO timestamps', () => {
    const sorted = sortAccomplishmentsDescending([
      accomplishment(1, '2026-03-01T00:00:00+00:00'),
      accomplishment(2, '2026-03-02T00:00:00+00:00'),
    ]);

    expect(sorted.map((a) => a.id)).toEqual([2, 1]);
  });

  it('breaks ties on the same date by id (later id first)', () => {
    const sorted = sortAccomplishmentsDescending([
      accomplishment(1, '2026-03-01'),
      accomplishment(3, '2026-03-01'),
      accomplishment(2, '2026-03-01'),
    ]);

    expect(sorted.map((a) => a.id)).toEqual([3, 2, 1]);
  });

  it('does not mutate the input array', () => {
    const input = [
      accomplishment(1, '2024-01-01'),
      accomplishment(2, '2026-01-01'),
    ];
    const snapshot = [...input];

    sortAccomplishmentsDescending(input);

    expect(input).toEqual(snapshot);
  });
});
