import { describe, expect, it } from 'vitest';
import { compactNumber, shortWeekLabel, weeklyChartPoints } from './reports';
import type { WeeklyReport } from './types';

describe('reports helpers', () => {
  it('formats whole and fractional numbers compactly', () => {
    expect(compactNumber(4)).toBe('4');
    expect(compactNumber(2.5)).toBe('2.5');
    expect(compactNumber(2.25)).toBe('2.25');
    expect(compactNumber(2.0)).toBe('2');
  });

  it('formats a week start as a short date', () => {
    const expected = new Date(2026, 8, 14).toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
    });
    expect(shortWeekLabel('2026-09-14')).toBe(expected);
    expect(shortWeekLabel('not-a-date')).toBe('not-a-date');
  });

  it('builds sorted chart points for counts and hours', () => {
    const weeks: WeeklyReport[] = [
      { weekStart: '2026-09-21', activityCount: 2, hours: 3 },
      { weekStart: '2026-09-14', activityCount: 5, hours: 4.5 },
    ];

    const counts = weeklyChartPoints(weeks, 'activities');
    expect(counts.map((point) => point.key)).toEqual([
      '2026-09-14',
      '2026-09-21',
    ]);
    expect(counts.map((point) => point.value)).toEqual([5, 2]);
    expect(counts.map((point) => point.displayValue)).toEqual(['5', '2']);

    const hours = weeklyChartPoints(weeks, 'hours');
    expect(hours.map((point) => point.value)).toEqual([4.5, 3]);
    expect(hours.map((point) => point.displayValue)).toEqual(['4.5', '3']);
  });
});
