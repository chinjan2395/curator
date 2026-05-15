/** Trailing slash stripped. Empty when unset (local dev: same origin + Vite proxy). */
export const apiBrowserBaseUrl = (import.meta.env.VITE_API_BASE_URL || '').trim().replace(/\/$/, '');

/**
 * Resolve a path like `/api/foo` to the configured API origin in production splits
 * (e.g. Netlify SPA + Railway API). Absolute http(s) inputs are normalized to pathname+search
 * then prefixed so a wrong host in stored embed code is corrected.
 */
export function apiUrlFromAny(urlOrPath) {
  if (!urlOrPath) return '';
  try {
    const base = typeof window !== 'undefined' ? window.location.href : 'http://localhost/';
    const u = new URL(urlOrPath, base);
    const path = u.pathname + u.search;
    if (apiBrowserBaseUrl) return `${apiBrowserBaseUrl}${path}`;
    return path;
  } catch {
    const p = urlOrPath.startsWith('/') ? urlOrPath : `/${urlOrPath}`;
    if (apiBrowserBaseUrl) return `${apiBrowserBaseUrl}${p}`;
    return p;
  }
}

/** Use in iframes / links: full URL when already absolute, else SPA origin + path (dev). */
export function absolutePageAssetUrl(href) {
  if (!href) return '';
  if (href.startsWith('http://') || href.startsWith('https://')) return href;
  if (typeof window === 'undefined') return href;
  const p = href.startsWith('/') ? href : `/${href}`;
  return `${window.location.origin}${p}`;
}
