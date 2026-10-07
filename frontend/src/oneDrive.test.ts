import { afterEach, describe, expect, it } from 'vitest';
import { consumeOneDriveFlag, oneDriveError } from './oneDrive';

afterEach(() => {
  window.history.replaceState({}, '', '/');
});

describe('oneDriveError', () => {
  it('surfaces the reason from an exchange failure', () => {
    expect(oneDriveError('exchange:Boom')).toBe(
      'OneDrive connection failed: Boom',
    );
  });

  it('maps known reasons to friendly messages', () => {
    expect(oneDriveError('not_configured')).toBe(
      'OneDrive is not configured on the server.',
    );
    expect(oneDriveError('microsoft')).toContain('Microsoft rejected');
    expect(oneDriveError('state_mismatch')).toContain('session was lost');
    expect(oneDriveError('missing_code')).toContain('no authorization code');
  });

  it('falls back to a generic message', () => {
    expect(oneDriveError(null)).toBe(
      'OneDrive connection failed or was cancelled.',
    );
    expect(oneDriveError('something_else')).toBe(
      'OneDrive connection failed or was cancelled.',
    );
  });
});

describe('consumeOneDriveFlag', () => {
  it('returns null when the flag is absent', () => {
    window.history.replaceState({}, '', '/jobs/42');

    expect(consumeOneDriveFlag()).toBeNull();
    expect(window.location.search).toBe('');
  });

  it('reads the connected flag and strips it from the URL', () => {
    window.history.replaceState({}, '', '/jobs/42?onedrive=connected');

    expect(consumeOneDriveFlag()).toEqual({ flag: 'connected', reason: null });
    expect(window.location.pathname).toBe('/jobs/42');
    expect(window.location.search).toBe('');
  });

  it('keeps the reason for error flags', () => {
    window.history.replaceState(
      {},
      '',
      '/resumes?onedrive=error&reason=microsoft',
    );

    expect(consumeOneDriveFlag()).toEqual({
      flag: 'error',
      reason: 'microsoft',
    });
    expect(window.location.search).toBe('');
  });

  it('only consumes the flag once', () => {
    window.history.replaceState({}, '', '/jobs/42?onedrive=connected');

    expect(consumeOneDriveFlag()).not.toBeNull();
    expect(consumeOneDriveFlag()).toBeNull();
  });
});
