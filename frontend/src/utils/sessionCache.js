const STORAGE_PREFIX = 'curator:cache:';

const memoryCache = new Map();
const inflightCache = new Map();

function storageKey(key) {
  return `${STORAGE_PREFIX}${key}`;
}

function canUseSessionStorage() {
  return typeof window !== 'undefined' && typeof window.sessionStorage !== 'undefined';
}

function normalizeEntry(entry) {
  if (!entry || typeof entry !== 'object' || !('value' in entry)) return null;
  const fetchedAt = Number(entry.fetchedAt);
  if (!Number.isFinite(fetchedAt)) return null;
  return {
    value: entry.value,
    fetchedAt,
  };
}

export function hydrateFromSession(key) {
  if (memoryCache.has(key)) return memoryCache.get(key);
  if (!canUseSessionStorage()) return null;

  try {
    const raw = window.sessionStorage.getItem(storageKey(key));
    if (!raw) return null;
    const entry = normalizeEntry(JSON.parse(raw));
    if (!entry) {
      window.sessionStorage.removeItem(storageKey(key));
      return null;
    }
    memoryCache.set(key, entry);
    return entry;
  } catch {
    return null;
  }
}

export function persistToSession(key, value, fetchedAt = Date.now()) {
  const entry = { value, fetchedAt };
  memoryCache.set(key, entry);

  if (canUseSessionStorage()) {
    try {
      window.sessionStorage.setItem(storageKey(key), JSON.stringify(entry));
    } catch {
      // Ignore storage quota/serialization issues and keep the memory copy.
    }
  }

  return entry;
}

export function isFresh(entry, ttlMs) {
  if (!entry) return false;
  if (!Number.isFinite(ttlMs) || ttlMs <= 0) return false;
  return Date.now() - Number(entry.fetchedAt) < ttlMs;
}

export function invalidate(key) {
  memoryCache.delete(key);
  inflightCache.delete(key);

  if (canUseSessionStorage()) {
    try {
      window.sessionStorage.removeItem(storageKey(key));
    } catch {
      // Ignore storage failures.
    }
  }
}

export function withDedupe(key, loader) {
  if (inflightCache.has(key)) return inflightCache.get(key);

  const promise = Promise.resolve()
    .then(loader)
    .finally(() => {
      inflightCache.delete(key);
    });

  inflightCache.set(key, promise);
  return promise;
}

export function clearAll() {
  memoryCache.clear();
  inflightCache.clear();

  if (canUseSessionStorage()) {
    try {
      Object.keys(window.sessionStorage)
        .filter((k) => k.startsWith(STORAGE_PREFIX))
        .forEach((k) => window.sessionStorage.removeItem(k));
    } catch {
      // Ignore storage failures.
    }
  }
}

export function updateCachedValue(key, updater, fallbackValue = null) {
  const current = hydrateFromSession(key);
  const nextValue = updater(current?.value ?? fallbackValue);
  return persistToSession(key, nextValue, Date.now());
}
