// Thin wrapper around the browser/OS `Notification` Web API so background job
// completions (AI generation, feed sync, scheduled posts, etc.) can reach the
// user even when the tab is hidden/unfocused and the in-app toast can't be seen.
// Stateless utility composable — no reactive state, safe to call anywhere.

function isSupported() {
  return typeof window !== 'undefined' && 'Notification' in window;
}

async function requestPermission() {
  if (!isSupported()) return 'unsupported';

  if (window.Notification.permission !== 'default') {
    return window.Notification.permission;
  }

  try {
    // Modern browsers return a Promise; older Safari/webkit versions only
    // support the callback form. Handle both defensively.
    const result = window.Notification.requestPermission((permission) => permission);
    if (result && typeof result.then === 'function') {
      return await result;
    }
    return window.Notification.permission;
  } catch {
    return window.Notification.permission || 'default';
  }
}

function notify(title, options = {}) {
  if (!isSupported() || window.Notification.permission !== 'granted') return null;

  const { onClick, ...rest } = options;

  try {
    const notification = new window.Notification(title, { icon: '/favicon.svg', ...rest });
    if (typeof onClick === 'function') {
      notification.onclick = () => {
        window.focus();
        onClick();
        notification.close();
      };
    }
    return notification;
  } catch {
    // Constructing Notification can throw in some contexts (e.g.
    // service-worker-only notification support on some mobile browsers).
    return null;
  }
}

function shouldNotify() {
  return typeof document !== 'undefined' && (document.hidden || !document.hasFocus());
}

export function useBrowserNotifications() {
  return { isSupported, requestPermission, notify, shouldNotify };
}
