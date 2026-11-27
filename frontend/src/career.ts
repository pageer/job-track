import type { Accomplishment } from './types';

/**
 * Format a date for display as year + month (e.g. "2026-03").
 * Accepts "YYYY-MM-DD" or a full ISO timestamp.
 */
export function formatYearMonth(date: string): string {
  return date.slice(0, 7);
}

/** The calendar day ("YYYY-MM-DD") of an ISO date or timestamp string. */
function calendarDay(date: string): string {
  return date.slice(0, 10);
}

/** First line of a freeform description (splits on LF, CRLF, or CR). */
export function firstLine(text: string): string {
  return text.split(/\r\n|\r|\n/)[0];
}

/** Whether a description has content beyond its first line. */
export function hasMoreThanFirstLine(text: string): boolean {
  return firstLine(text) !== text;
}

/**
 * Reverse chronological order (newest first); entries on the same date break
 * ties by id (later id first). Returns a new array; does not mutate input.
 */
export function sortAccomplishmentsDescending(
  accomplishments: Accomplishment[],
): Accomplishment[] {
  return [...accomplishments].sort((a, b) => {
    const dayA = calendarDay(a.date);
    const dayB = calendarDay(b.date);
    if (dayA !== dayB) {
      return dayA < dayB ? 1 : -1;
    }
    return b.id - a.id;
  });
}
