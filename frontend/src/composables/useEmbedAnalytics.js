import { apiUrlFromAny } from '../config/api.js';

export function postLinkHref(post) {
  return String(post?.post_url || post?.video_url || '#');
}

/**
 * Record an embed analytics event (post click or share click).
 * Uses the same public endpoint as curator-embed.js.
 */
export function trackEmbedPostEvent(publicKey, postId, eventType = 'post_click', targetUrl = '') {
  if (!publicKey || !postId) return;

  const endpoint = apiUrlFromAny(
    `/api/public/feeds/${encodeURIComponent(publicKey)}/posts/${encodeURIComponent(postId)}/events`,
  );
  const payload = JSON.stringify({
    event_type: eventType,
    target_url: targetUrl || '',
    page_url: typeof window !== 'undefined' ? window.location.href : '',
    referrer: typeof document !== 'undefined' ? document.referrer || '' : '',
  });

  try {
    fetch(endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: payload,
      credentials: 'omit',
      keepalive: true,
    }).catch(() => {});
    return;
  } catch {
    // fall through to sendBeacon
  }

  try {
    if (navigator.sendBeacon) {
      const blob = new Blob([payload], { type: 'application/json' });
      navigator.sendBeacon(endpoint, blob);
    }
  } catch {
    // Analytics must never block preview navigation.
  }
}
