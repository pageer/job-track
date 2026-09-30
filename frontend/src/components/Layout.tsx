import { NavLink } from 'react-router-dom';
import { useAuth } from '../auth';
import { jobsNavTarget, useJobSearches } from '../jobSearches';
import type { ReactNode } from 'react';

export default function Layout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();
  const { searches } = useJobSearches();
  const isAdmin = user?.roles.includes('ROLE_ADMIN') ?? false;
  const jobsNav = jobsNavTarget(searches);

  const navLinkClass = ({ isActive }: { isActive: boolean }) =>
    'nav-link' + (isActive ? ' active' : '');

  return (
    <div className="app-shell">
      <header className="app-header">
        <NavLink to="/" className="brand">
          Job Track
        </NavLink>
        <nav className="main-nav">
          <NavLink to={jobsNav.to} end={jobsNav.end} className={navLinkClass}>
            {jobsNav.label}
          </NavLink>
          <NavLink to="/resumes" className={navLinkClass}>
            Resumes
          </NavLink>
          <NavLink to="/cover-letters" className={navLinkClass}>
            Cover Letters
          </NavLink>
          <NavLink to="/activities" className={navLinkClass}>
            Activity Tracker
          </NavLink>
          <NavLink to="/todos" className={navLinkClass}>
            To-dos
          </NavLink>
          <NavLink to="/networking" className={navLinkClass}>
            Networking
          </NavLink>
          {isAdmin && (
            <NavLink to="/users" className={navLinkClass}>
              Users
            </NavLink>
          )}
        </nav>
        <div className="user-menu">
          <span className="user-name">{user?.name}</span>
          <button
            type="button"
            className="btn btn-sm btn-ghost"
            onClick={() => void logout()}
          >
            Log out
          </button>
        </div>
      </header>
      <main className="app-main">{children}</main>
    </div>
  );
}
