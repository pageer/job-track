import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api, ApiError, getCsrfToken, setCsrfToken } from './api';

describe('api client', () => {
  const fetchMock = vi.fn();

  function jsonResponse(data: unknown, status = 200): Response {
    return new Response(JSON.stringify(data), {
      status,
      headers: { 'Content-Type': 'application/json' },
    });
  }

  beforeEach(() => {
    setCsrfToken(null);
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('sends requests with credentials', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ ok: true }));
    await api.get('/api/jobs');
    expect(fetchMock).toHaveBeenCalledWith(
      '/api/jobs',
      expect.objectContaining({ credentials: 'include' }),
    );
  });

  it('does not send the CSRF header on GET requests', async () => {
    setCsrfToken('abc');
    fetchMock.mockResolvedValue(jsonResponse({}));
    await api.get<unknown>('/api/jobs');
    const [, init] = fetchMock.mock.calls[0];
    expect(init.headers.has('X-CSRF-TOKEN')).toBe(false);
  });

  it('attaches the CSRF token to state-changing requests once set', async () => {
    setCsrfToken('abc');
    fetchMock.mockResolvedValue(jsonResponse({}));
    await api.post('/api/jobs', { title: 'X' });
    const [, init] = fetchMock.mock.calls[0];
    expect(init.headers.get('X-CSRF-TOKEN')).toBe('abc');
  });

  it('never sends the CSRF token to the login endpoint', async () => {
    setCsrfToken('abc');
    fetchMock.mockResolvedValue(jsonResponse({}));
    await api.post('/api/auth/login', { email: 'a', password: 'b' });
    const [, init] = fetchMock.mock.calls[0];
    expect(init.headers.has('X-CSRF-TOKEN')).toBe(false);
  });

  it('exposes and stores the CSRF token', () => {
    expect(getCsrfToken()).toBeNull();
    setCsrfToken('xyz');
    expect(getCsrfToken()).toBe('xyz');
  });

  it('sends a JSON body and Content-Type for state-changing requests', async () => {
    fetchMock.mockResolvedValue(jsonResponse({}));
    await api.patch('/api/jobs/1', { status: 'applied' });
    const [, init] = fetchMock.mock.calls[0];
    expect(init.headers.get('Content-Type')).toBe('application/json');
    expect(init.body).toBe(JSON.stringify({ status: 'applied' }));
  });

  it('parses JSON responses', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ status: 'applied' }));
    const data = await api.get<{ status: string }>('/api/jobs/1');
    expect(data.status).toBe('applied');
  });

  it('throws an ApiError carrying the server error message', async () => {
    fetchMock.mockResolvedValue(jsonResponse({ error: 'Not allowed' }, 400));
    const err = await api.post('/api/jobs', {}).catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiError);
    expect((err as ApiError).status).toBe(400);
    expect((err as ApiError).message).toBe('Not allowed');
  });

  it('throws a generic ApiError for non-JSON error responses', async () => {
    fetchMock.mockResolvedValue(
      new Response('<html>oops</html>', {
        status: 500,
        headers: { 'Content-Type': 'text/html' },
      }),
    );
    const err = await api.delete('/api/jobs/1').catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiError);
    expect((err as ApiError).status).toBe(500);
    expect((err as ApiError).message).toBe('Request failed (500).');
  });

  it('throws a network ApiError when fetch rejects', async () => {
    fetchMock.mockRejectedValue(new TypeError('network down'));
    const err = await api.get('/api/jobs').catch((e: unknown) => e);
    expect(err).toBeInstanceOf(ApiError);
    expect((err as ApiError).status).toBe(0);
    expect((err as ApiError).message).toBe(
      'Could not reach the server. Please try again.',
    );
  });
});
