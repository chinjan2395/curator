import { defineStore } from 'pinia';
import { createEcho } from '../plugins/echo';

function isAdminRole(role) {
  return role === 'admin' || role === 'superadmin';
}

export const useRealtimeStore = defineStore('realtime', {
  state: () => ({
    connected: false,
    echo: null,
    handlers: {
      scheduledPost: [],
      feedSync: [],
      notification: [],
      aiGeneration: [],
      analyticsInsights: [],
      adminSync: [],
      duplicateScan: [],
    },
  }),
  getters: {
    isConfigured: () => Boolean(import.meta.env.VITE_REVERB_APP_KEY),
    isEnabled: (state) => Boolean(import.meta.env.VITE_REVERB_APP_KEY && state.connected),
  },
  actions: {
    connect(token, userId, userRole = null) {
      if (!token || !userId || !import.meta.env.VITE_REVERB_APP_KEY) {
        return;
      }

      this.disconnect();

      const echo = createEcho(token);
      if (!echo) {
        return;
      }

      this.echo = echo;
      const channel = echo.private(`user.${userId}`);

      channel.listen('.scheduled-post.status-changed', (payload) => {
        this._emit('scheduledPost', payload);
      });
      channel.listen('.feed-sync.updated', (payload) => {
        this._emit('feedSync', payload);
      });
      channel.listen('.notification.created', (payload) => {
        this._emit('notification', payload);
      });
      channel.listen('.ai-generation.updated', (payload) => {
        this._emit('aiGeneration', payload);
      });
      channel.listen('.analytics-insights.updated', (payload) => {
        this._emit('analyticsInsights', payload);
      });
      channel.listen('.duplicate-scan.completed', (payload) => {
        this._emit('duplicateScan', payload);
      });

      if (isAdminRole(userRole)) {
        const adminChannel = echo.private(`admin.${userId}`);
        adminChannel.listen('.admin-sync.updated', (payload) => {
          this._emit('adminSync', payload);
        });
      }

      this.connected = true;
    },
    disconnect() {
      if (this.echo) {
        this.echo.disconnect();
        this.echo = null;
      }
      this.connected = false;
    },
    on(event, handler) {
      if (!this.handlers[event]) {
        return () => {};
      }
      this.handlers[event].push(handler);
      return () => {
        this.handlers[event] = this.handlers[event].filter((h) => h !== handler);
      };
    },
    _emit(event, payload) {
      for (const handler of this.handlers[event] || []) {
        handler(payload);
      }
    },
  },
});
