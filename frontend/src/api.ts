/**
 * Small API client for the Job Track backend.
 *
 * Auth model:
 *  - The session is stored in a SameSite=Lax cookie, so all requests use
 *    `credentials: 'include'`.
 *  - All state-changing requests (except /api/auth/login) must carry the CSRF
 *    token in the `X-CSRF-TOKEN` header. Tokens are issued by the login,
 *    /api/auth/me and /api/setup/status responses.
 */

let csrfToken: string | null = null;

export function getCsrfToken(): string | null {
  return csrfToken;
}

export function setCsrfToken(token: string | null): void {
  csrfToken = token;
}

export class ApiError extends Error {
  readonly status: number;

  constructor(status: number, message: string) {
    super(message);
    this.status = status;
  }
}

interface RequestOptions {
  method?: string;
  body?: unknown;
  /** Send as multipart/form-data instead of JSON. */
  formData?: FormData;
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = 'GET', body, formData } = options;

  const headers = new Headers();
  if (formData) {
    // Let the browser set the multipart boundary.
  } else if (body !== undefined) {
    headers.set('Content-Type', 'application/json');
  }
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method) && path !== '/api/auth/login' && csrfToken) {
    headers.set('X-CSRF-TOKEN', csrfToken);
  }

  let payload: BodyInit | undefined;
  if (formData) {
    payload = formData;
  } else if (body !== undefined) {
    payload = JSON.stringify(body);
  }

  let response: Response;
  try {
    response = await fetch(path, {
      method,
      headers,
      credentials: 'include',
      body: payload,
    });
  } catch {
    throw new ApiError(0, 'Could not reach the server. Please try again.');
  }

  const contentType = response.headers.get('content-type') ?? '';
  let data: unknown = null;
  if (contentType.includes('application/json')) {
    data = await response.json().catch(() => null);
  } else {
    // Some error responses (e.g. HTML error pages) are not JSON.
    data = await response.text().catch(() => null);
  }

  if (!response.ok) {
    if (data && typeof data === 'object' && 'error' in data && typeof (data as { error: unknown }).error === 'string') {
      throw new ApiError(response.status, (data as { error: string }).error);
    }
    throw new ApiError(response.status, `Request failed (${response.status}).`);
  }

  return data as T;
}

export const api = {
  get: <T>(path: string) => request<T>(path),
  post: <T>(path: string, body?: unknown) => request<T>(path, { method: 'POST', body }),
  patch: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PATCH', body }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
  postForm: <T>(path: string, formData: FormData) => request<T>(path, { method: 'POST', formData }),
};
