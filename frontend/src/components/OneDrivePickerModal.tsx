import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { Link } from 'react-router-dom';
import { api, ApiError } from '../api';
import type { OneDriveFile, OneDriveStatus } from '../types';
import { formatDate, formatFileSize } from '../utils';
import ErrorBanner from './ErrorBanner';
import Modal from './Modal';

interface OneDrivePickerModalProps {
  onSelect: (file: OneDriveFile) => void;
  onClose: () => void;
  noun?: string;
}

function statusFolders(status: OneDriveStatus): string[] {
  if (status.folders.length > 0) {
    return status.folders;
  }
  if (status.folderPath) {
    return [status.folderPath];
  }
  return [];
}

export default function OneDrivePickerModal({
  onSelect,
  onClose,
  noun = 'resume',
}: OneDrivePickerModalProps) {
  const [status, setStatus] = useState<OneDriveStatus | null>(null);
  const [files, setFiles] = useState<OneDriveFile[]>([]);
  const [search, setSearch] = useState('');
  const [loadingFiles, setLoadingFiles] = useState(false);
  const [connecting, setConnecting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selectedFolder, setSelectedFolder] = useState<string>('');

  const loadStatus = useCallback(async () => {
    try {
      const s = await api.get<OneDriveStatus>('/api/onedrive/status');
      setStatus(s);
      setError(null);

      return s;
    } catch (err) {
      setStatus(null);
      setError(
        err instanceof ApiError
          ? err.message
          : 'Failed to reach the OneDrive integration.',
      );

      return null;
    }
  }, []);

  const loadFiles = useCallback(
    async (folder: string) => {
      setLoadingFiles(true);
      setError(null);
      try {
        const url = folder
          ? `/api/onedrive/files?folder=${encodeURIComponent(folder)}`
          : '/api/onedrive/files';
        const data = await api.get<{
          files: OneDriveFile[];
          folderPath?: string;
        }>(url);
        setFiles(data.files);
        if (data.folderPath) {
          setSelectedFolder(data.folderPath);
        }
      } catch (err) {
        if (err instanceof ApiError && err.status === 409) {
          // Token expired or revoked; the server removed it.
          const fresh = await loadStatus();
          if (fresh && !fresh.connected) {
            setError(
              'Your OneDrive connection has expired. Please connect again.',
            );
          }
        } else {
          setError(
            err instanceof ApiError
              ? err.message
              : 'Failed to load files from OneDrive.',
          );
        }
      } finally {
        setLoadingFiles(false);
      }
    },
    [loadStatus],
  );

  useEffect(() => {
    void (async () => {
      const s = await loadStatus();
      if (s?.connected) {
        const folders = statusFolders(s);
        const initial = s.folderPath ?? folders[0] ?? '';
        setSelectedFolder(initial);
        if (initial) {
          await loadFiles(initial);
        }
      }
    })();
  }, [loadStatus, loadFiles]);

  async function handleConnect() {
    setConnecting(true);
    setError(null);
    try {
      const returnTo = window.location.pathname + window.location.search;
      const data = await api.get<{ url: string }>(
        `/api/onedrive/auth-url?returnTo=${encodeURIComponent(returnTo)}`,
      );
      window.location.href = data.url;
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : 'Failed to start the OneDrive connection.',
      );
      setConnecting(false);
    }
  }

  async function handleDisconnect() {
    setConnecting(true);
    setError(null);
    try {
      await api.post('/api/onedrive/disconnect');
      setStatus((s) =>
        s
          ? {
              ...s,
              connected: false,
              accountEmail: null,
              accountDisplayName: null,
            }
          : s,
      );
      setFiles([]);
      setSelectedFolder(status?.folderPath ?? '');
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : 'Failed to disconnect OneDrive.',
      );
    } finally {
      setConnecting(false);
    }
  }

  const query = search.trim().toLowerCase();
  const filtered = query
    ? files.filter((f) => f.name.toLowerCase().includes(query))
    : files;

  let content: ReactNode;
  if (status !== null && !status.clientConfigured) {
    content = (
      <p className="muted">
        OneDrive is not configured on the server. Set the ONEDRIVE_CLIENT_ID
        environment variable to enable it.
      </p>
    );
  } else if (status !== null && !status.connected) {
    content = (
      <>
        <p className="muted">
          Connect your personal Microsoft account to choose a {noun} from one of
          your configured OneDrive folders.
        </p>
        <button
          type="button"
          className="btn btn-primary"
          onClick={() => void handleConnect()}
          disabled={connecting}
        >
          {connecting ? 'Connecting…' : 'Connect OneDrive'}
        </button>
      </>
    );
  } else if (status !== null) {
    const account = status.accountDisplayName ?? status.accountEmail;
    const folders = statusFolders(status);

    if (folders.length === 0) {
      content = (
        <>
          <p className="muted">
            No OneDrive folders are configured. Add one in your{' '}
            <Link to="/profile" onClick={onClose}>
              profile settings
            </Link>
            .
          </p>
          <div className="form-actions">
            <button type="button" className="btn btn-ghost" onClick={onClose}>
              Cancel
            </button>
          </div>
        </>
      );
    } else {
      content = (
        <>
          <div className="field">
            <span>Folder</span>
            <select
              value={selectedFolder || folders[0]}
              onChange={(e) => {
                const folder = e.target.value;
                setSelectedFolder(folder);
                void loadFiles(folder);
              }}
            >
              {folders.map((folder) => (
                <option key={folder} value={folder}>
                  {folder}
                </option>
              ))}
            </select>
          </div>
          <div className="filter-bar">
            <label className="filter-label">
              <input
                type="search"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Filter files by name…"
                autoFocus
              />
            </label>
            <span className="muted">
              {account ? `Connected as ${account}` : ''}
            </span>
          </div>
          {loadingFiles ? (
            <div className="spinner" aria-label="Loading files" />
          ) : filtered.length === 0 ? (
            <p className="muted">No {noun}s found.</p>
          ) : (
            <ul className="file-list">
              {filtered.map((f) => (
                <li key={f.id}>
                  <button
                    type="button"
                    className="file-row"
                    onClick={() => onSelect(f)}
                  >
                    <span className="file-name">{f.name}</span>
                    <span className="file-meta">
                      {formatFileSize(f.size)}
                      {f.lastModifiedDateTime
                        ? ` · modified ${formatDate(f.lastModifiedDateTime)}`
                        : ''}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
          <div className="form-actions">
            <button
              type="button"
              className="btn btn-ghost"
              onClick={() => void handleDisconnect()}
              disabled={connecting}
            >
              Disconnect
            </button>
            <button type="button" className="btn btn-ghost" onClick={onClose}>
              Cancel
            </button>
          </div>
        </>
      );
    }
  } else {
    content = <div className="spinner" aria-label="Loading OneDrive status" />;
  }

  return (
    <Modal title={`Add ${noun} from OneDrive`} onClose={onClose}>
      <div className="form">
        <ErrorBanner message={error} />
        {content}
      </div>
    </Modal>
  );
}
