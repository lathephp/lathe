/** Every failed request becomes one ApiError, with the status and the parsed body. */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly data: unknown,
    message?: string,
  ) {
    super(message ?? `Request failed with status ${String(status)}`);
    this.name = 'ApiError';
  }
}

export interface RequestOptions {
  method?: string;
  body?: unknown;
  headers?: Record<string, string>;
  signal?: AbortSignal;
}

function xsrfToken(): string | null {
  if (typeof document === 'undefined') {
    return null;
  }

  const match = /(?:^|;\s*)XSRF-TOKEN=([^;]*)/.exec(document.cookie);

  return match?.[1] === undefined ? null : decodeURIComponent(match[1]);
}

/** The headers every panel request sends: the AJAX marker and the CSRF token when there is one. */
export function csrfHeaders(): Record<string, string> {
  const token = xsrfToken();

  return token === null ? { 'X-Requested-With': 'XMLHttpRequest' } : { 'X-Requested-With': 'XMLHttpRequest', 'X-XSRF-TOKEN': token };
}

/**
 * The panel's HTTP client: sends the CSRF token, asks for JSON, answers null for a 204,
 * and turns every failure, a malformed body included, into an ApiError.
 */
export async function request<T = unknown>(input: string, options: RequestOptions = {}): Promise<T | null> {
  const headers: Record<string, string> = {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
    ...options.headers,
  };
  const token = xsrfToken();

  if (token !== null) {
    headers['X-XSRF-TOKEN'] = token;
  }

  let body: string | undefined;

  if (options.body !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(options.body);
  }

  let response: Response;

  try {
    response = await fetch(input, {
      method: options.method ?? 'GET',
      headers,
      body,
      credentials: 'same-origin',
      signal: options.signal,
    });
  } catch (error) {
    throw new ApiError(0, null, error instanceof Error ? error.message : 'Network error');
  }

  if (response.status === 204) {
    return null;
  }

  const text = await response.text();
  let data: unknown = null;

  if (text !== '') {
    try {
      data = JSON.parse(text);
    } catch {
      throw new ApiError(response.status, text, 'The server sent a response that is not JSON.');
    }
  }

  if (!response.ok) {
    throw new ApiError(response.status, data);
  }

  return data as T;
}
