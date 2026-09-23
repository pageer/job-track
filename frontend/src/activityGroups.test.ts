import { describe, expect, it } from 'vitest';
import { groupActivities } from './activityGroups';
import type { JobActivity } from './types';

function makeActivity(partial: Partial<JobActivity>): JobActivity {
  return {
    id: 1,
    description: 'Submit resume',
    details: null,
    date: '2026-09-21',
    hoursSpent: 1,
    createdAt: '2026-09-21T10:00:00+00:00',
    ...partial,
  };
}

const weekStart = '2026-09-21';

describe('groupActivities', () => {
  it('groups activities by day within a single week and sums hours', () => {
    const activities = [
      makeActivity({ id: 1, date: weekStart, hoursSpent: 1.5 }),
      makeActivity({ id: 2, date: weekStart, hoursSpent: 0.25 }),
      makeActivity({ id: 3, date: '2026-09-22', hoursSpent: 3 }),
    ];

    const weeks = groupActivities(activities);

    expect(weeks).toHaveLength(1);
    expect(weeks[0].key).toBe(weekStart);
    expect(weeks[0].startDate).toBe(weekStart);
    expect(weeks[0].endDate).toBe('2026-09-27');
    expect(weeks[0].days.map((d) => d.key)).toEqual(['2026-09-22', weekStart]);
    expect(weeks[0].days[1].totalHours).toBe(1.75);
    expect(weeks[0].days[1].activities).toHaveLength(2);
    expect(weeks[0].totalHours).toBe(4.75);
  });

  it('rounds fractional hour sums to two decimals', () => {
    const weeks = groupActivities([
      makeActivity({ date: weekStart, hoursSpent: 0.1 }),
      makeActivity({ date: weekStart, hoursSpent: 0.2 }),
    ]);

    expect(weeks[0].days[0].totalHours).toBe(0.3);
    expect(weeks[0].totalHours).toBe(0.3);
  });

  it('splits weeks at Monday boundary', () => {
    const sunday = '2026-09-20';
    const monday = '2026-09-21';

    const weeks = groupActivities([
      makeActivity({ date: sunday, hoursSpent: 2 }),
      makeActivity({ date: monday, hoursSpent: 1 }),
    ]);

    expect(weeks).toHaveLength(2);
    expect(weeks[0].key).toBe(monday);
    expect(weeks[0].startDate).toBe(monday);
    expect(weeks[0].totalHours).toBe(1);
    expect(weeks[1].key).toBe('2026-09-14');
    expect(weeks[1].endDate).toBe(sunday);
    expect(weeks[1].totalHours).toBe(2);
  });

  it('sorts weeks in reverse chronological order', () => {
    const weeks = groupActivities([
      makeActivity({ date: '2026-09-01', hoursSpent: 1 }),
      makeActivity({ date: '2026-09-21', hoursSpent: 1 }),
      makeActivity({ date: '2026-10-05', hoursSpent: 1 }),
    ]);

    expect(weeks.map((w) => w.key)).toEqual([
      '2026-10-05',
      '2026-09-21',
      '2026-08-31',
    ]);
  });

  it('returns an empty list for no activities', () => {
    expect(groupActivities([])).toEqual([]);
  });
});
