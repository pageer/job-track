import type { JobActivity } from './types';

export interface ActivityDay {
  key: string;
  activities: JobActivity[];
  totalHours: number;
}

export interface ActivityWeek {
  key: string;
  startDate: string;
  endDate: string;
  days: ActivityDay[];
  totalHours: number;
}

export function dateKey(date: string): string {
  return date.slice(0, 10);
}

function parseKey(key: string): { y: number; m: number; d: number } {
  const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(key);
  if (!match) {
    return { y: 0, m: 1, d: 1 };
  }
  return { y: Number(match[1]), m: Number(match[2]), d: Number(match[3]) };
}

function formatUtcKey(date: Date): string {
  const year = date.getUTCFullYear();
  const month = String(date.getUTCMonth() + 1).padStart(2, '0');
  const day = String(date.getUTCDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function addDays(key: string, days: number): string {
  const { y, m, d } = parseKey(key);
  return formatUtcKey(new Date(Date.UTC(y, m - 1, d) + days * 86400000));
}

function mondayOf(key: string): string {
  const { y, m, d } = parseKey(key);
  const weekday = new Date(Date.UTC(y, m - 1, d)).getUTCDay();
  const offsetToMonday = (weekday + 6) % 7;
  return addDays(key, -offsetToMonday);
}

function roundHours(hours: number): number {
  return Math.round(hours * 100) / 100;
}

export function groupActivities(activities: JobActivity[]): ActivityWeek[] {
  const byDay = new Map<string, JobActivity[]>();
  for (const activity of activities) {
    const key = dateKey(activity.date);
    const list = byDay.get(key) ?? [];
    list.push(activity);
    byDay.set(key, list);
  }

  const weeks = new Map<string, ActivityWeek>();
  for (const [dayKey, dayActivities] of byDay) {
    const monday = mondayOf(dayKey);
    let week = weeks.get(monday);
    if (!week) {
      week = {
        key: monday,
        startDate: monday,
        endDate: addDays(monday, 6),
        days: [],
        totalHours: 0,
      };
      weeks.set(monday, week);
    }

    const total = dayActivities.reduce((sum, a) => sum + a.hoursSpent, 0);
    week.days.push({
      key: dayKey,
      activities: dayActivities,
      totalHours: roundHours(total),
    });
  }

  const result = [...weeks.values()].sort((a, b) => b.key.localeCompare(a.key));

  for (const week of result) {
    week.days.sort((a, b) => b.key.localeCompare(a.key));
    week.totalHours = roundHours(
      week.days.reduce((sum, day) => sum + day.totalHours, 0),
    );
  }

  return result;
}
