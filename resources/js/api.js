/**
 * Cliente HTTP compartilhado pela SPA.
 *
 * As rotas mutáveis do Laravel exigem que o cookie CSRF seja inicializado
 * antes da chamada. Manter esse detalhe aqui evita repetir a lógica em cada
 * tela e facilita trocar a estratégia de autenticação no futuro.
 */
function xsrfToken() {
  const cookie = document.cookie
    .split("; ")
    .find((item) => item.startsWith("XSRF-TOKEN="));

  return decodeURIComponent(cookie?.split("=").slice(1).join("=") || "");
}

function notifyRequestError(code, path) {
  // Login failures and form validation are explained next to their fields.
  if (path === '/auth/login' && code === 401) return;
  if (typeof window !== 'undefined') window.dispatchEvent(new CustomEvent('spot:request-error', { detail: { code } }));
}

async function fetchWithRecovery(url, options, path, handledStatuses = []) {
  try {
    const response = await fetch(url, options);
    if (response.status >= 400 && ![400, 409, 422, ...handledStatuses].includes(response.status)) notifyRequestError(response.status, path);
    return response;
  } catch (error) {
    if (error.name !== 'AbortError') notifyRequestError('network', path);
    throw error;
  }
}

// For existing web endpoints that already provide their own CSRF headers.
export async function spotFetch(url, options = {}) {
  return fetchWithRecovery(url, options, new URL(url, window.location.origin).pathname);
}

export async function sessionRequest(path, options = {}) {
  const { handledStatuses = [], ...requestOptions } = options;
  const method = (options.method || "GET").toUpperCase();
  const changesState = !["GET", "HEAD", "OPTIONS"].includes(method);

  if (changesState) {
    const csrf = await fetchWithRecovery("/sanctum/csrf-cookie", { credentials: "same-origin", headers: { Accept: "application/json" } }, path);
    if (!csrf.ok) return csrf;
  }

  const response = await fetchWithRecovery(`/api/v1${path}`, {
    ...requestOptions,
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      // O navegador define o boundary multipart ao enviar FormData.
      ...(options.body instanceof FormData ? {} : { "Content-Type": "application/json" }),
      ...(changesState ? { "X-XSRF-TOKEN": xsrfToken() } : {}),
      ...options.headers,
    },
  }, path, handledStatuses);
  if (changesState && response.ok && typeof window !== 'undefined') {
    // Agenda depois do retorno para não sobrescrever a atualização local do formulário.
    window.setTimeout(() => {
      window.dispatchEvent(new Event('spot:data-changed'));
      try { window.localStorage.setItem('spot.data.changed', `${Date.now()}:${Math.random()}`); } catch { /* Sem armazenamento, a aba atual continua atualizando. */ }
    }, 0);
  }
  return response;
}

/**
 * Variante para telas que só precisam do JSON e de uma mensagem de erro já
 * normalizada. Use sessionRequest diretamente quando a tela precisar ler
 * status ou headers da resposta.
 */
export async function apiJson(path, options = {}) {
  const response = await sessionRequest(path, options);
  const body = response.status === 204 ? null : await response.json();

  if (!response.ok) {
    const validationError = Object.values(body?.errors || {}).flat()[0];
    throw new Error(body?.message || validationError || "Não foi possível concluir a operação.");
  }

  return body;
}
