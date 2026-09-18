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

export async function sessionRequest(path, options = {}) {
  const method = (options.method || "GET").toUpperCase();
  const changesState = !["GET", "HEAD", "OPTIONS"].includes(method);

  if (changesState) {
    await fetch("/sanctum/csrf-cookie", { credentials: "same-origin" });
  }

  return fetch(`/api/v1${path}`, {
    ...options,
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(changesState ? { "X-XSRF-TOKEN": xsrfToken() } : {}),
      ...options.headers,
    },
  });
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
