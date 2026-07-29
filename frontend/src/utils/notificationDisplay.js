const categoryMap = {
  success: { label: 'Success', badgeVariant: 'success', icon: 'check', iconWrapClass: 'bg-emerald-50 text-emerald-700 ring-emerald-100', railClass: 'bg-emerald-400' },
  warning: { label: 'Warning', badgeVariant: 'warning', icon: 'alert', iconWrapClass: 'bg-amber-50 text-amber-700 ring-amber-100', railClass: 'bg-amber-400' },
  sync: { label: 'Sync', badgeVariant: 'info', icon: 'sync', iconWrapClass: 'bg-sky-50 text-sky-700 ring-sky-100', railClass: 'bg-sky-400' },
  ai: { label: 'AI', badgeVariant: 'purple', icon: 'sparkles', iconWrapClass: 'bg-violet-50 text-violet-700 ring-violet-100', railClass: 'bg-violet-400' },
  default: { label: 'Update', badgeVariant: 'default', icon: 'bell', iconWrapClass: 'bg-slate-100 text-slate-700 ring-slate-200', railClass: 'bg-slate-400' },
};

/**
 * Infer a display category for a notification based on its type/title/body.
 */
export function inferNotificationCategory(notification) {
  const text = `${notification.type || ''} ${notification.title || ''} ${notification.body || ''}`.toLowerCase();
  if (/(fail|error|warning|retry|blocked|sync_failed)/.test(text)) return 'warning';
  if (/(generated|approved|published|complete|success|ready|done)/.test(text)) return 'success';
  if (/(sync|ingest|import|webhook|feed)/.test(text)) return 'sync';
  if (/(ai|llm|caption|variant|score|draft|prompt|image)/.test(text)) return 'ai';
  return 'default';
}

/**
 * Resolve the badge/icon/rail display metadata for a notification.
 */
export function getNotificationMeta(notification) {
  const category = inferNotificationCategory(notification);
  return categoryMap[category] || categoryMap.default;
}
