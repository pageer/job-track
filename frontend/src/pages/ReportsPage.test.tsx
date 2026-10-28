import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import { api } from '../api';
import type { ReportSummary } from '../types';
import { shortWeekLabel } from '../reports';
import ReportsPage from './ReportsPage';

const populated: ReportSummary = {
  totalApplications: 8,
  applicationsWithInterview: 3,
  totalActivities: 5,
  totalHours: 7.5,
  weekly: [
    { weekStart: '2026-09-21', activityCount: 2, hours: 3 },
    { weekStart: '2026-09-14', activityCount: 3, hours: 4.5 },
  ],
};

function statValue(label: string): HTMLElement {
  const stat = screen.getByText(label).closest('.report-stat');
  if (!(stat instanceof HTMLElement)) {
    throw new Error(`No stat card found for ${label}`);
  }
  return stat;
}

describe('ReportsPage', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('renders totals and weekly charts', async () => {
    vi.spyOn(api, 'get').mockResolvedValue(populated);

    render(<ReportsPage />);

    expect(
      await screen.findByRole('heading', { name: 'Reports' }),
    ).toBeInTheDocument();

    expect(
      within(statValue('Jobs applied to')).getByText('8'),
    ).toBeInTheDocument();
    expect(
      within(statValue('Applications with interviews')).getByText('3'),
    ).toBeInTheDocument();
    expect(
      within(statValue('Activities recorded')).getByText('5'),
    ).toBeInTheDocument();
    expect(
      within(statValue('Hours logged')).getByText('7.5'),
    ).toBeInTheDocument();

    expect(
      screen.getByRole('img', { name: 'Weekly activity counts' }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('img', { name: 'Weekly hours logged' }),
    ).toBeInTheDocument();
    expect(screen.getAllByText(shortWeekLabel('2026-09-14'))).toHaveLength(2);
    expect(screen.getAllByText(shortWeekLabel('2026-09-21'))).toHaveLength(2);
  });

  it('shows empty chart messages when there is no activity', async () => {
    vi.spyOn(api, 'get').mockResolvedValue({
      totalApplications: 0,
      applicationsWithInterview: 0,
      totalActivities: 0,
      totalHours: 0,
      weekly: [],
    });

    render(<ReportsPage />);

    expect(
      await screen.findByText('No activities logged yet.'),
    ).toBeInTheDocument();
    expect(screen.getByText('No hours logged yet.')).toBeInTheDocument();
  });
});
