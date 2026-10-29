import { useCallback, useEffect, useState, type FormEvent } from 'react';
import { useAuth } from '../auth';
import { api, ApiError } from '../api';
import type { OneDriveStatus } from '../types';
import ErrorBanner from '../components/ErrorBanner';

export default function ProfilePage() {
  const { user } = useAuth();
  const [status, setStatus] = useState<OneDriveStatus | null>(null);
  const [folders, setFolders] = useState<string[]>([]);
  const [newFolder, setNewFolder] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [folderError, setFolderError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await api.get<OneDriveStatus>('/api/onedrive/status');
      setStatus(data);
      setFolders(data.folders ?? (data.folderPath ? [data.folderPath] : []));
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : 'Failed to load your profile settings.',
      );
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  function handleAdd(event: FormEvent) {
    event.preventDefault();
    const folder = newFolder
      .trim()
      .replace(/^\/+/, '')
      .replace(/\/+$/, '')
      .trim();

    if (!folder) {
      setFolderError('Enter a folder name.');
      return;
    }
    if (folder.length > 255) {
      setFolderError('Folder paths must be 255 characters or fewer.');
      return;
    }
    if (
      folders.some(
        (existing) => existing.toLowerCase() === folder.toLowerCase(),
      )
    ) {
      setFolderError('That folder is already configured.');
      return;
    }

    setFolders([...folders, folder]);
    setNewFolder('');
    setFolderError(null);
    setSaved(false);
  }

  function handleRemove(folder: string) {
    setFolders(folders.filter((existing) => existing !== folder));
    setFolderError(null);
    setSaved(false);
  }

  async function handleSave() {
    setSaving(true);
    setError(null);
    setFolderError(null);
    setSaved(false);
    try {
      const data = await api.put<{ folders: string[] }>(
        '/api/onedrive/settings',
        { folders },
      );
      setFolders(data.folders);
      setStatus((current) =>
        current
          ? {
              ...current,
              folders: data.folders,
              folderPath: data.folders[0] ?? null,
            }
          : current,
      );
      setSaved(true);
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : 'Failed to save your OneDrive folders.',
      );
    } finally {
      setSaving(false);
    }
  }

  const account = status?.accountDisplayName ?? status?.accountEmail;

  return (
    <div className="page">
      <div className="page-header">
        <div>
          <h1>Profile</h1>
          <p className="page-subtitle">Manage your account and OneDrive.</p>
        </div>
      </div>
      <ErrorBanner message={error} />

      {loading ? (
        <div className="spinner" aria-label="Loading profile" />
      ) : (
        <>
          <section className="card profile-card">
            <h2>Account</h2>
            <dl className="profile-details">
              <dt className="profile-label">Name</dt>
              <dd>{user?.name}</dd>
              <dt className="profile-label">Email</dt>
              <dd>{user?.email}</dd>
            </dl>
          </section>

          <section className="card profile-card">
            <h2>OneDrive folders</h2>
            <p className="muted">
              Choose the folders whose files appear in the OneDrive picker. The
              first folder is selected by default.
            </p>
            {status !== null &&
              (!status.clientConfigured ? (
                <p className="muted">
                  OneDrive is not configured on the server.
                </p>
              ) : (
                <p className="muted">
                  {status.connected
                    ? `Connected${account ? ` as ${account}` : ''}.`
                    : 'OneDrive is not connected yet. You can connect from the file picker.'}
                </p>
              ))}

            {folders.length === 0 ? (
              <p className="muted">No OneDrive folders configured yet.</p>
            ) : (
              <ul className="folder-list">
                {folders.map((folder) => (
                  <li key={folder} className="folder-row">
                    <span className="folder-path">{folder}</span>
                    <button
                      type="button"
                      className="btn btn-sm btn-danger-ghost"
                      aria-label={`Remove ${folder}`}
                      onClick={() => handleRemove(folder)}
                    >
                      Remove
                    </button>
                  </li>
                ))}
              </ul>
            )}

            <form className="form" onSubmit={handleAdd}>
              <div className="field">
                <label htmlFor="new-onedrive-folder">Add a folder</label>
                <div className="field-row">
                  <input
                    id="new-onedrive-folder"
                    value={newFolder}
                    onChange={(event) => setNewFolder(event.target.value)}
                    placeholder="e.g. Resumes"
                  />
                  <button type="submit" className="btn btn-primary">
                    Add
                  </button>
                </div>
                <small>
                  Use a path relative to your OneDrive root, such as &quot;Job
                  Search/Resumes&quot;.
                </small>
              </div>
              <ErrorBanner message={folderError} />
            </form>

            <div className="form-actions">
              {saved && (
                <span className="muted" role="status">
                  Saved.
                </span>
              )}
              <button
                type="button"
                className="btn btn-primary"
                onClick={() => void handleSave()}
                disabled={saving}
              >
                {saving ? 'Saving…' : 'Save folders'}
              </button>
            </div>
          </section>
        </>
      )}
    </div>
  );
}
