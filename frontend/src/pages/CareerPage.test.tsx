import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { api } from '../api';
import type { Accomplishment } from '../types';
import CareerPage from './CareerPage';

const older: Accomplishment = {
  id: 1,
  date: '2024-01-15',
  company: 'Old Co',
  description: 'Led a migration.\nCut deploy time in half.',
};

const newer: Accomplishment = {
  id: 2,
  date: '2026-03-10',
  company: 'New Co',
  description: 'Shipped the billing rewrite.',
};

function mockList(items: Accomplishment[]) {
  vi.spyOn(api, 'get').mockResolvedValue(items);
}

describe('CareerPage', () => {
  beforeEach(() => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('shows a Career heading and add button', async () => {
    mockList([]);
    render(<CareerPage />);

    expect(
      await screen.findByRole('heading', { name: 'Career' }),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('button', { name: 'Add accomplishment' }),
    ).toBeInTheDocument();
  });

  it('shows an empty state when there are no accomplishments', async () => {
    mockList([]);
    render(<CareerPage />);

    expect(
      await screen.findByText('No accomplishments yet.'),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('button', { name: 'Add your first accomplishment' }),
    ).toBeInTheDocument();
  });

  it('lists accomplishments in reverse chronological order with year + month dates', async () => {
    mockList([older, newer]); // server order intentionally not sorted
    render(<CareerPage />);

    await screen.findByText('Shipped the billing rewrite.');

    const items = screen.getAllByRole('listitem');
    expect(items).toHaveLength(2);
    expect(within(items[0]).getByText('2026-03')).toBeInTheDocument();
    expect(within(items[0]).getByText('New Co')).toBeInTheDocument();
    expect(within(items[1]).getByText('2024-01')).toBeInTheDocument();
    expect(within(items[1]).getByText('Old Co')).toBeInTheDocument();
    // only year + month is shown, not the day
    expect(screen.queryByText(/2024-01-15/)).not.toBeInTheDocument();
  });

  it('shows the first line and expands to the entire entry', async () => {
    mockList([older]);
    render(<CareerPage />);

    expect(await screen.findByText('Led a migration.')).toBeInTheDocument();
    expect(
      screen.queryByText(/Cut deploy time in half/),
    ).not.toBeInTheDocument();

    const toggle = screen.getByRole('button', { name: 'Show more' });
    expect(toggle).toHaveAttribute('aria-expanded', 'false');

    fireEvent.click(toggle);

    expect(screen.getByText(/Cut deploy time in half/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Show less' })).toHaveAttribute(
      'aria-expanded',
      'true',
    );

    fireEvent.click(screen.getByRole('button', { name: 'Show less' }));
    expect(
      screen.queryByText(/Cut deploy time in half/),
    ).not.toBeInTheDocument();
  });

  it('adds a new accomplishment', async () => {
    mockList([]);
    const post = vi.spyOn(api, 'post').mockResolvedValue(newer);
    render(<CareerPage />);

    await screen.findByText('No accomplishments yet.');
    fireEvent.click(screen.getByRole('button', { name: 'Add accomplishment' }));

    const dialog = await screen.findByRole('dialog', {
      name: 'Add accomplishment',
    });
    fireEvent.change(within(dialog).getByLabelText('Date'), {
      target: { value: '2026-03-10' },
    });
    fireEvent.change(within(dialog).getByLabelText('Company name'), {
      target: { value: 'New Co' },
    });
    fireEvent.change(within(dialog).getByLabelText('Description'), {
      target: { value: 'Shipped the billing rewrite.' },
    });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    expect(post).toHaveBeenCalledWith('/api/accomplishments', {
      date: '2026-03-10',
      company: 'New Co',
      description: 'Shipped the billing rewrite.',
    });
  });

  it('edits an existing accomplishment', async () => {
    mockList([newer]);
    const patch = vi.spyOn(api, 'patch').mockResolvedValue(newer);
    render(<CareerPage />);

    await screen.findByText('Shipped the billing rewrite.');
    fireEvent.click(screen.getByRole('button', { name: 'Edit' }));

    const dialog = await screen.findByRole('dialog', {
      name: 'Edit accomplishment',
    });
    expect(within(dialog).getByLabelText('Date')).toHaveValue('2026-03-10');
    expect(within(dialog).getByLabelText('Company name')).toHaveValue('New Co');
    expect(within(dialog).getByLabelText('Description')).toHaveValue(
      'Shipped the billing rewrite.',
    );

    fireEvent.change(within(dialog).getByLabelText('Company name'), {
      target: { value: 'Globex' },
    });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Save' }));

    expect(patch).toHaveBeenCalledWith('/api/accomplishments/2', {
      date: '2026-03-10',
      company: 'Globex',
      description: 'Shipped the billing rewrite.',
    });
  });

  it('deletes an accomplishment after confirmation', async () => {
    mockList([newer]);
    const del = vi.spyOn(api, 'delete').mockResolvedValue({ success: true });
    render(<CareerPage />);

    await screen.findByText('Shipped the billing rewrite.');
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }));

    expect(window.confirm).toHaveBeenCalled();
    expect(del).toHaveBeenCalledWith('/api/accomplishments/2');
  });

  it('does not delete when confirmation is declined', async () => {
    vi.mocked(window.confirm).mockReturnValue(false);
    mockList([newer]);
    const del = vi.spyOn(api, 'delete').mockResolvedValue({ success: true });
    render(<CareerPage />);

    await screen.findByText('Shipped the billing rewrite.');
    fireEvent.click(screen.getByRole('button', { name: 'Delete' }));

    expect(del).not.toHaveBeenCalled();
  });

  it('shows an error banner when loading fails', async () => {
    vi.spyOn(api, 'get').mockRejectedValue(new Error('boom'));
    render(<CareerPage />);

    expect(
      await screen.findByText('Failed to load accomplishments.'),
    ).toBeInTheDocument();
  });
});
