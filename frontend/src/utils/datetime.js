/**
 * Convert a datetime-local value (YYYY-MM-DDTHH:mm, browser local) to UTC ISO for the API.
 */
export function localDatetimeInputToUtcIso(localValue) {
  if (!localValue) return '';
  const date = new Date(localValue);
  if (Number.isNaN(date.getTime())) return '';
  return date.toISOString();
}

/**
 * Format a UTC ISO datetime for display in the user's local timezone.
 */
export function formatScheduledAt(utcIso) {
  if (!utcIso) return '';
  const date = new Date(utcIso);
  if (Number.isNaN(date.getTime())) return utcIso;
  return date.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

/**
 * Local calendar day key (YYYY-MM-DD) from a UTC ISO datetime for grouping.
 */
export function localDayKeyFromUtcIso(utcIso) {
  if (!utcIso) return '';
  const date = new Date(utcIso);
  if (Number.isNaN(date.getTime())) return (utcIso || '').slice(0, 10);
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  return `${y}-${m}-${d}`;
}

/**
 * Human-readable local date label from a YYYY-MM-DD day key.
 */
export function formatLocalDayLabel(dayKey) {
  if (!dayKey) return '';
  const [y, m, d] = dayKey.split('-').map(Number);
  if (!y || !m || !d) return dayKey;
  return new Date(y, m - 1, d).toLocaleDateString(undefined, {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });
}

/**
 * UTC ISO range covering the current local calendar month (for calendar API filters).
 */
export function localMonthUtcRange(referenceDate = new Date()) {
  const start = new Date(referenceDate.getFullYear(), referenceDate.getMonth(), 1);
  const end = new Date(referenceDate.getFullYear(), referenceDate.getMonth() + 1, 0, 23, 59, 59, 999);
  return {
    from: start.toISOString(),
    to: end.toISOString(),
  };
}

/**
 * Human-readable relative time label ("X unit(s) ago"/"from now", "Just now", "Soon").
 */
export function formatRelativeTime(value) {
  if (!value) return 'Just now';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return 'Just now';

  const diffMs = date.getTime() - Date.now();
  const absSeconds = Math.round(Math.abs(diffMs) / 1000);
  const units = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
  ];

  for (const [unit, seconds] of units) {
    if (absSeconds >= seconds) {
      const count = Math.max(1, Math.round(absSeconds / seconds));
      return `${count} ${unit}${count === 1 ? '' : 's'} ${diffMs > 0 ? 'from now' : 'ago'}`;
    }
  }

  return diffMs > 0 ? 'Soon' : 'Just now';
}

/**
 * Minimum value for datetime-local inputs (now, in local time).
 */
export function minLocalDatetimeInputValue(date = new Date()) {
  const y = date.getFullYear();
  const m = String(date.getMonth() + 1).padStart(2, '0');
  const d = String(date.getDate()).padStart(2, '0');
  const h = String(date.getHours()).padStart(2, '0');
  const min = String(date.getMinutes()).padStart(2, '0');
  return `${y}-${m}-${d}T${h}:${min}`;
}
