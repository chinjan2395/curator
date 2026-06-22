import { onMounted, onUnmounted } from 'vue';
import { useRealtimeStore } from '../stores/realtime';

/**
 * Subscribe to a realtime event; poll when Reverb/Echo is not configured.
 *
 * @param {{
 *   event: string,
 *   onEvent: (payload: unknown) => void,
 *   poll?: () => void | Promise<void>,
 *   pollIntervalMs?: number,
 * }} options
 */
export function useRealtimeWithFallback({
  event,
  onEvent,
  poll,
  pollIntervalMs = 30_000,
}) {
  const realtime = useRealtimeStore();
  let unsubscribe = null;
  let pollTimer = null;

  onMounted(() => {
    unsubscribe = realtime.on(event, onEvent);

    if (!realtime.isConfigured && poll) {
      pollTimer = window.setInterval(() => {
        if (document.hidden) return;
        poll();
      }, pollIntervalMs);
    }
  });

  onUnmounted(() => {
    if (unsubscribe) unsubscribe();
    if (pollTimer) window.clearInterval(pollTimer);
  });
}
