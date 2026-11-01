import { NavLink } from 'react-router-dom';
import { useAuth } from '../auth';
import { jobsNavTarget, useJobSearches } from '../jobSearches';
import { useEffect, useRef, useState, type ReactNode } from 'react';
// Vite rewrites this import to a string URL, so the image resolves in both dev
// and production. Small SVGs are inlined as data URIs in the production build.
import logoUrl from '../assets/logo.svg';

export default function Layout({ children }: { children: ReactNode }) {
  const { user, logout } = useAuth();
  const { searches } = useJobSearches();
  const isAdmin = user?.roles.includes('ROLE_ADMIN') ?? false;
  const jobsNav = jobsNavTarget(searches);
  const [menuOpen, setMenuOpen] = useState(false);
  const [accountOpen, setAccountOpen] = useState(false);
  const [adminOpen, setAdminOpen] = useState(false);
  const userMenuRef = useRef<HTMLDivElement>(null);

  const navLinkClass = ({ isActive }: { isActive: boolean }) =>
    'nav-link' + (isActive ? ' active' : '');

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        setMenuOpen(false);
        setAccountOpen(false);
        setAdminOpen(false);
      }
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, []);

  useEffect(() => {
    const onResize = () => {
      if (window.innerWidth >= 800) {
        setMenuOpen(false);
      } else {
        setAccountOpen(false);
        setAdminOpen(false);
      }
    };
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);

  useEffect(() => {
    if (!accountOpen) return;
    const onPointerDown = (e: MouseEvent) => {
      if (
        userMenuRef.current &&
        !userMenuRef.current.contains(e.target as Node)
      ) {
        setAccountOpen(false);
        setAdminOpen(false);
      }
    };
    document.addEventListener('mousedown', onPointerDown);
    return () => document.removeEventListener('mousedown', onPointerDown);
  }, [accountOpen]);

  function closeMenu() {
    setMenuOpen(false);
  }

  function closeAccount() {
    setAccountOpen(false);
    setAdminOpen(false);
  }

  function toggleAccount() {
    setAccountOpen((open) => !open);
    setAdminOpen(false);
  }

  return (
    <div className="app-shell">
      <header className="app-header">
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
        <NavLink to="/" className="brand" onClick={closeMenu}>
          <img src={logoUrl} alt="Job Track" />
        </NavLink>
        <nav
          id="main-nav"
          className={`main-nav ${menuOpen ? 'open' : ''}`}
          aria-hidden={!menuOpen}
        >
          <div className="mobile-user-name">{user?.name}</div>
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
          <NavLink to="/reports" className={navLinkClass} onClick={closeMenu}>
            Reports
          </NavLink>
          <div className="mobile-account">
            <div className="nav-divider" aria-hidden="true" />
            <NavLink to="/profile" className={navLinkClass} onClick={closeMenu}>
              Profile
            </NavLink>
            {isAdmin && (
              <div className="mobile-admin">
                <div className="nav-section-label">Admin</div>
                <NavLink
                  to="/users"
                  className={({ isActive }) =>
                    navLinkClass({ isActive }) + ' nav-link-sub'
                  }
                  onClick={closeMenu}
                >
                  Users
                </NavLink>
              </div>
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
          </div>
        </nav>
        <div className="user-menu" ref={userMenuRef}>
          <button
            type="button"
            className="user-name"
            aria-haspopup="true"
            aria-expanded={accountOpen}
            aria-controls="user-dropdown"
            onClick={toggleAccount}
          >
            {user?.name}
            <span className="user-name-chevron" aria-hidden="true">
              ▾
            </span>
          </button>
          {accountOpen && (
            <div className="user-dropdown" id="user-dropdown">
              <NavLink
                to="/profile"
                className={({ isActive }) =>
                  'user-dropdown-item' + (isActive ? ' active' : '')
                }
                onClick={closeAccount}
              >
                Profile
              </NavLink>
              {isAdmin && (
                <div className="user-submenu">
                  <button
                    type="button"
                    className="user-dropdown-item user-submenu-toggle"
                    aria-expanded={adminOpen}
                    onClick={() => setAdminOpen((open) => !open)}
                  >
                    Admin
                    <span className="user-submenu-chevron" aria-hidden="true">
                      ▸
                    </span>
                  </button>
                  {adminOpen && (
                    <div className="user-submenu-items">
                      <NavLink
                        to="/users"
                        className={({ isActive }) =>
                          'user-dropdown-item user-submenu-item' +
                          (isActive ? ' active' : '')
                        }
                        onClick={closeAccount}
                      >
                        Users
                      </NavLink>
                    </div>
                  )}
                </div>
              )}
              <div className="user-dropdown-divider" aria-hidden="true" />
              <button
                type="button"
                className="user-dropdown-item user-dropdown-logout"
                onClick={() => {
                  closeAccount();
                  void logout();
                }}
              >
                Log out
              </button>
            </div>
          )}
        </div>
      </header>
      {menuOpen && (
        <div className="nav-backdrop" onClick={closeMenu} aria-hidden="true" />
      )}
      <main className="app-main">{children}</main>
    </div>
  );
}
