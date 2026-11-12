// Only a random change marker is shared: never credentials or activation links.
export const sessionChangeKey = 'spot.session.changed';
let identity = window.__SPOT__?.user?.id ?? null;

export function setSessionIdentity(id) { identity = id ?? null; }
export function sessionIdentityHeaders() {
  return identity === null ? {} : { 'X-Spot-User': String(identity) };
}
export function announceSessionChange() {
  try { localStorage.setItem(sessionChangeKey, `${Date.now()}:${Math.random()}`); }
  catch { /* Other tabs also verify their session when focused. */ }
}
