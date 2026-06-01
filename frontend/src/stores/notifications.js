import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

export const useNotificationsStore = defineStore('notifications', {
  state: () => ({
    items: [],
    unreadCount: 0,
    loading: false,
    error: null,
  }),
  actions: {
    async fetchAll() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get('/api/notifications');
        const payload = data.data || data;
        this.items = payload.notifications || [];
        this.unreadCount = payload.unread_count || 0;
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to load notifications';
      } finally {
        this.loading = false;
      }
    },
    async markAllRead() {
      try {
        await axios.post('/api/notifications/read-all');
        this.unreadCount = 0;
        this.items = this.items.map((n) => ({ ...n, read_at: new Date().toISOString() }));
        useToastStore().success('All notifications marked read');
      } catch (e) {
        throw e;
      }
    },
  },
});
