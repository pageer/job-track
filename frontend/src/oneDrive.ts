export interface OneDriveFlag {
  flag: string;
  reason: string | null;
}

export function oneDriveError(reason: string | null): string {
  if (reason?.startsWith('exchange:')) {
    return `OneDrive connection failed: ${reason.slice('exchange:'.length)}`;
  }
  switch (reason) {
    case 'not_configured':
      return 'OneDrive is not configured on the server.';
    case 'microsoft':
      return 'Microsoft rejected the connection request. Check the OneDrive app registration.';
    case 'state_mismatch':
      return 'The connection session was lost or expired. Please try again.';
    case 'missing_code':
      return 'The OneDrive connection returned no authorization code.';
    default:
      return 'OneDrive connection failed or was cancelled.';
  }
}

export function consumeOneDriveFlag(): OneDriveFlag | null {
  const params = new URLSearchParams(window.location.search);
  const flag = params.get('onedrive');
  if (!flag) {
    return null;
  }
  const reason = params.get('reason');
  window.history.replaceState({}, '', window.location.pathname);

  return { flag, reason };
}
