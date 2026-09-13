import { describe, expect, it } from 'vitest';
import {
  formatDate,
  formatDateTime,
  toDateInputValue,
  toDateTimeInputValue,
  formatFileSize,
} from './utils';

describe('formatDate', () => {
  it('returns an empty string for missing values', () => {
    expect(formatDate(null)).toBe('');
    expect(formatDate(undefined)).toBe('');
    expect(formatDate('')).toBe('');
  });

  it('returns the input unchanged when it is not a valid date', () => {
    expect(formatDate('not-a-date')).toBe('not-a-date');
  });

  it('formats a valid ISO date', () => {
    const iso = '2026-09-13T14:30:00Z';
    const expected = new Date(iso).toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
    expect(formatDate(iso)).toBe(expected);
  });
});

describe('formatDateTime', () => {
  it('returns an empty string for missing values', () => {
    expect(formatDateTime(null)).toBe('');
    expect(formatDateTime(undefined)).toBe('');
    expect(formatDateTime('')).toBe('');
  });

  it('returns the input unchanged when it is not a valid date', () => {
    expect(formatDateTime('garbage')).toBe('garbage');
  });

  it('formats a valid ISO datetime', () => {
    const iso = '2026-09-13T14:30:00Z';
    const expected = new Date(iso).toLocaleString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
    expect(formatDateTime(iso)).toBe(expected);
  });
});

describe('toDateInputValue', () => {
  it('returns an empty string for missing values', () => {
    expect(toDateInputValue(null)).toBe('');
    expect(toDateInputValue(undefined)).toBe('');
    expect(toDateInputValue('')).toBe('');
  });

  it('returns the input unchanged when it is not a valid date', () => {
    expect(toDateInputValue('nope')).toBe('nope');
  });

  it('zero-pads month and day and uses local date parts', () => {
    const iso = '2026-09-13T14:30:00Z';
    const d = new Date(iso);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    expect(toDateInputValue(iso)).toBe(`${year}-${month}-${day}`);
  });
});

describe('toDateTimeInputValue', () => {
  it('returns an empty string for missing values', () => {
    expect(toDateTimeInputValue(null)).toBe('');
    expect(toDateTimeInputValue(undefined)).toBe('');
    expect(toDateTimeInputValue('')).toBe('');
  });

  it('returns the input unchanged when it is not a valid date', () => {
    expect(toDateTimeInputValue('nope')).toBe('nope');
  });

  it('zero-pads all parts and uses local date parts', () => {
    const iso = '2026-09-13T14:30:00Z';
    const d = new Date(iso);
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    expect(toDateTimeInputValue(iso)).toBe(
      `${year}-${month}-${day}T${hours}:${minutes}`,
    );
  });
});

describe('formatFileSize', () => {
  it('returns an empty string for missing values', () => {
    expect(formatFileSize(null)).toBe('');
    expect(formatFileSize(undefined)).toBe('');
  });

  it('formats bytes under 1 KB as-is', () => {
    expect(formatFileSize(0)).toBe('0 B');
    expect(formatFileSize(512)).toBe('512 B');
    expect(formatFileSize(1023)).toBe('1023 B');
  });

  it('formats kilobytes with one decimal', () => {
    expect(formatFileSize(1024)).toBe('1.0 KB');
    expect(formatFileSize(1536)).toBe('1.5 KB');
  });

  it('formats megabytes with one decimal', () => {
    expect(formatFileSize(1024 * 1024)).toBe('1.0 MB');
    expect(formatFileSize(3.5 * 1024 * 1024)).toBe('3.5 MB');
  });
});
