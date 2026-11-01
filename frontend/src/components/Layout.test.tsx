import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import Layout from './Layout';

const auth = vi.hoisted(() => ({
  user: {
    id: 1,
    name: 'Alice Example',
    email: 'alice@example.com',
    roles: ['ROLE_USER'],
    createdAt: '2026-01-01T00:00:00Z',
  },
  logout: vi.fn(),
}));

vi.mock('../auth', () => ({
  useAuth: () => auth,
}));

vi.mock('../jobSearches', () => ({
  jobsNavTarget: () => ({ to: '/searches', label: 'Job Searches', end: true }),
  useJobSearches: () => ({ searches: [], refresh: vi.fn() }),
}));

function renderLayout() {
  return render(
    <MemoryRouter>
      <Layout>
        <div>Page content</div>
      </Layout>
    </MemoryRouter>,
  );
}

function openAccountMenu() {
  const trigger = screen.getByRole('button', { name: /Alice Example/ });
  fireEvent.click(trigger);
  return trigger.parentElement as HTMLElement;
}

describe('Layout account menu', () => {
  beforeEach(() => {
    auth.logout.mockClear();
    auth.user.roles = ['ROLE_USER'];
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('opens with Profile and Log out links', () => {
    renderLayout();
    const trigger = screen.getByRole('button', { name: /Alice Example/ });
    expect(trigger).toHaveAttribute('aria-expanded', 'false');

    const menu = openAccountMenu();

    expect(trigger).toHaveAttribute('aria-expanded', 'true');
    expect(within(menu).getByRole('link', { name: 'Profile' })).toHaveAttribute(
      'href',
      '/profile',
    );
    expect(
      within(menu).getByRole('button', { name: 'Log out' }),
    ).toBeInTheDocument();
    expect(
      within(menu).queryByRole('button', { name: 'Admin' }),
    ).not.toBeInTheDocument();
  });

  it('nests Users under an Admin sub-menu for admins', () => {
    auth.user.roles = ['ROLE_ADMIN'];
    renderLayout();
    const menu = openAccountMenu();

    expect(
      within(menu).queryByRole('link', { name: 'Users' }),
    ).not.toBeInTheDocument();

    fireEvent.click(within(menu).getByRole('button', { name: 'Admin' }));

    expect(within(menu).getByRole('link', { name: 'Users' })).toHaveAttribute(
      'href',
      '/users',
    );
  });

  it('logs out from the account menu', () => {
    renderLayout();
    const menu = openAccountMenu();

    fireEvent.click(within(menu).getByRole('button', { name: 'Log out' }));

    expect(auth.logout).toHaveBeenCalledTimes(1);
  });

  it('closes when clicking outside', () => {
    renderLayout();
    const menu = openAccountMenu();
    expect(within(menu).getByRole('link', { name: 'Profile' })).toBeVisible();

    fireEvent.mouseDown(document.body);

    expect(
      within(menu).queryByRole('link', { name: 'Profile' }),
    ).not.toBeInTheDocument();
  });
});
