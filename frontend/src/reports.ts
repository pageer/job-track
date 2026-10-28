import type { WeeklyReport } from './types';

export type WeeklyMetric = 'activities' | 'hours';

export interface WeeklyChartPoint {
  key: string;
  label: string;
  value: number;
  displayValue: string;
}

export function shortWeekLabel(weekStart: string): string {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(weekStart);
  if (!match) {
    return weekStart;
  }

  const date = new Date(
    Number(match[1]),
    Number(match[2]) - 1,
    Number(match[3]),
  );
  return date.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
  });
}

export function compactNumber(value: number): string {
  const rounded = Math.round(value * 100) / 100;
  return Number.isInteger(rounded)
    ? String(rounded)
    : rounded.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
}

export function weeklyChartPoints(
  weeks: WeeklyReport[],
  metric: WeeklyMetric,
): WeeklyChartPoint[] {
  return [...weeks]
    .sort((a, b) => a.weekStart.localeCompare(b.weekStart))
    .map((week) => {
      const value = metric === 'hours' ? week.hours : week.activityCount;
      return {
        key: week.weekStart,
        label: shortWeekLabel(week.weekStart),
        value,
        displayValue: compactNumber(value),
      };
    });
}
