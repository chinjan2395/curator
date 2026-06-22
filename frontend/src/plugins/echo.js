import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

/**
 * @param {string} token Sanctum bearer token
 * @returns {Echo|null}
 */
export function createEcho(token) {
  const key = import.meta.env.VITE_REVERB_APP_KEY;
  if (!key || !token) {
    return null;
  }

  const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
  const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);

  return new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/api/broadcasting/auth',
    auth: {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    },
  });
}
