import { afterEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { api } from '../api';
import { JobSearchesProvider } from '../jobSearches';
import type { JobSearch } from '../types';
import DashboardPage from './DashboardPage';

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

function renderDashboard() {
  return render(
    <MemoryRouter initialEntries={['/']}>
      <JobSearchesProvider>
        <Routes>
          <Route path="/" element={<DashboardPage />} />
          <Route path="/searches/:searchId" element={<div>Job list</div>} />
        </Routes>
      </JobSearchesProvider>
    </MemoryRouter>,
  );
}

describe('DashboardPage', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('redirects to the only active job search', async () => {
    vi.spyOn(api, 'get').mockResolvedValue([
      search({ id: 7 }),
      search({ id: 1, name: 'Closed', endDate: '2026-07-01' }),
    ]);

    renderDashboard();

    expect(await screen.findByText('Job list')).toBeInTheDocument();
  });

  it('stays on the job search list when several searches are active', async () => {
    vi.spyOn(api, 'get').mockResolvedValue([
      search({ id: 1 }),
      search({ id: 2 }),
    ]);

    renderDashboard();

    expect(
      await screen.findByRole('heading', { name: 'Job Searches' }),
    ).toBeInTheDocument();
    expect(screen.queryByText('Job list')).not.toBeInTheDocument();
  });
});
