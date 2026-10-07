import { NavLink } from 'react-router-dom';
import { useAuth } from '../auth';
import { jobsNavTarget, useJobSearches } from '../jobSearches';
import { useEffect, useState, type ReactNode } from 'react';

export default function Layout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();
  const { searches } = useJobSearches();
  const isAdmin = user?.roles.includes('ROLE_ADMIN') ?? false;
  const jobsNav = jobsNavTarget(searches);
  const [menuOpen, setMenuOpen] = useState(false);

  const navLinkClass = ({ isActive }: { isActive: boolean }) =>
    'nav-link' + (isActive ? ' active' : '');

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setMenuOpen(false);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);

  useEffect(() => {
    if (!menuOpen) return;
    const onResize = () => {
      if (window.innerWidth >= 800) {
        setMenuOpen(false);
      }
    };
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, [menuOpen]);

  function closeMenu() {
    setMenuOpen(false);
  }

  return (
    <div className="app-shell">
      <header className="app-header">
        <NavLink to="/" className="brand" onClick={closeMenu}>
          Job Track
        </NavLink>
        <button
          type="button"
          className="mobile-menu-btn"
          aria-label={menuOpen ? 'Close navigation' : 'Open navigation'}
          aria-expanded={menuOpen}
          aria-controls="main-nav"
          onClick={() => setMenuOpen((o) => !o)}
        >
          <span className="hamburger-line" />
          <span className="hamburger-line" />
          <span className="hamburger-line" />
        </button>
        <nav
          id="main-nav"
          className={`main-nav ${menuOpen ? 'open' : ''}`}
          aria-hidden={!menuOpen}
        >
          <NavLink
            to={jobsNav.to}
            end={jobsNav.end}
            className={navLinkClass}
            onClick={closeMenu}
          >
            {jobsNav.label}
          </NavLink>

          <NavLink
            to="/activities"
            className={navLinkClass}
            onClick={closeMenu}
          >
            Activity Tracker
          </NavLink>
          <NavLink to="/todos" className={navLinkClass} onClick={closeMenu}>
            To-dos
          </NavLink>
          <NavLink
            to="/networking"
            className={navLinkClass}
            onClick={closeMenu}
          >
            Networking
          </NavLink>
          {isAdmin && (
            <NavLink to="/users" className={navLinkClass} onClick={closeMenu}>
              Users
            </NavLink>
          )}
          <button
            type="button"
            className="mobile-logout"
            onClick={() => {
              closeMenu();
              void logout();
            }}
          >
            Log out
          </button>
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
      {menuOpen && (
        <div className="nav-backdrop" onClick={closeMenu} aria-hidden="true" />
      )}
      <main className="app-main">{children}</main>
    </div>
  );
}
