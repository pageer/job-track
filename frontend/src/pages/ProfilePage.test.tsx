import { afterEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { api } from '../api';
import type { OneDriveStatus } from '../types';
import ProfilePage from './ProfilePage';

vi.mock('../auth', () => ({
  useAuth: () => ({
    user: {
      id: 1,
      name: 'Alice Example',
      email: 'alice@example.com',
      roles: ['ROLE_USER'],
      createdAt: '2026-01-01T00:00:00Z',
    },
  }),
}));

const status: OneDriveStatus = {
  clientConfigured: true,
  connected: true,
  accountEmail: 'alice@example.com',
  accountDisplayName: 'Alice',
  folderPath: 'Resumes',
  folders: ['Resumes'],
};

describe('ProfilePage', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('loads, edits, and saves OneDrive folders', async () => {
    vi.spyOn(api, 'get').mockResolvedValue(status);
    const put = vi
      .spyOn(api, 'put')
      .mockResolvedValue({ folders: ['Old Resumes'] });

    render(<ProfilePage />);

    expect(await screen.findByText('Resumes')).toBeInTheDocument();
    expect(screen.getByText('Alice Example')).toBeInTheDocument();
    expect(screen.getByText('Connected as Alice.')).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText('Add a folder'), {
      target: { value: 'Old Resumes' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Add' }));
    expect(screen.getByText('Old Resumes')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Remove Resumes' }));
    expect(screen.queryByText('Resumes')).not.toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Save folders' }));

    await waitFor(() => {
      expect(put).toHaveBeenCalledWith('/api/onedrive/settings', {
        folders: ['Old Resumes'],
      });
    });
    expect(await screen.findByText('Saved.')).toBeInTheDocument();
  });

  it('rejects duplicate folders before saving', async () => {
    vi.spyOn(api, 'get').mockResolvedValue(status);

    render(<ProfilePage />);

    const input = await screen.findByLabelText('Add a folder');
    fireEvent.change(input, { target: { value: 'resumes' } });
    fireEvent.click(screen.getByRole('button', { name: 'Add' }));

    expect(
      screen.getByText('That folder is already configured.'),
    ).toBeInTheDocument();
  });
});
