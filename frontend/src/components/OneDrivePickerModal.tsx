import { useCallback, useEffect, useState, type ReactNode } from 'react';
import { api, ApiError } from '../api';
import type { OneDriveFile, OneDriveStatus } from '../types';
import { formatDate, formatFileSize } from '../utils';
import ErrorBanner from './ErrorBanner';
import Modal from './Modal';

interface OneDrivePickerModalProps {
  onSelect: (file: OneDriveFile) => void;
  onClose: () => void;
}

export default function OneDrivePickerModal({
  onSelect,
  onClose,
}: OneDrivePickerModalProps) {
  const [status, setStatus] = useState<OneDriveStatus | null>(null);
  const [files, setFiles] = useState<OneDriveFile[]>([]);
  const [search, setSearch] = useState('');
  const [loadingFiles, setLoadingFiles] = useState(false);
  const [connecting, setConnecting] = useState(false);
  const [error, setError] = useState<string | null>(null);

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

  const loadFiles = useCallback(async () => {
    setLoadingFiles(true);
    setError(null);
    try {
      const data = await api.get<{ files: OneDriveFile[] }>(
        '/api/onedrive/files',
      );
      setFiles(data.files);
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
  }, [loadStatus]);

  useEffect(() => {
    void (async () => {
      const s = await loadStatus();
      if (s?.connected) {
        await loadFiles();
      }
    })();
  }, [loadStatus, loadFiles]);

  async function handleConnect() {
    setConnecting(true);
    setError(null);
    try {
      const data = await api.get<{ url: string }>('/api/onedrive/auth-url');
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
          Connect your personal Microsoft account to choose a resume from the
          configured OneDrive folder.
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
    content = (
      <>
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
        </div>
        <p className="muted">
          {account ? `Connected as ${account} — ` : ''}folder &ldquo;
          {status.folderPath}&rdquo;
        </p>
        {loadingFiles ? (
          <div className="spinner" aria-label="Loading files" />
        ) : filtered.length === 0 ? (
          <p className="muted">No resumes found.</p>
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
  } else {
    content = <div className="spinner" aria-label="Loading OneDrive status" />;
  }

  return (
    <Modal title="Add resume from OneDrive" onClose={onClose}>
      <div className="form">
        <ErrorBanner message={error} />
        {content}
      </div>
    </Modal>
  );
}
