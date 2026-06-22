import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';
import { useRealtimeStore } from './realtime';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    token: localStorage.getItem('token') || null,
    loggingOut: false,
    error: null,
    brokenCredentials: [],
    syncSummary: {
      new_post_count: 0,
      scheduler_synced_post_count: 0,
      scheduler_unread_count: 0,
      broken_credentials: [],
      sync_notifications_seen_at: null,
    },
  }),
  actions: {
    init() {
      if (this.token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
      }
    },
    async login(email, password) {
      try {
        const response = await axios.post('/api/login', { email, password });
        const payload = response.data.data || response.data;
        this.token = payload.token;
        this.user = payload.user;
        this.error = null;
        localStorage.setItem('token', this.token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
        this.fetchSyncSummary();
        useRealtimeStore().connect(this.token, this.user?.id, this.user?.role);
      } catch (err) {
        this.error = err.response?.data?.message || 'Login failed';
      }
    },
    async register(name, email, password) {
      try {
        const response = await axios.post('/api/register', { name, email, password });
        const payload = response.data.data || response.data;
        this.token = payload.token;
        this.user = payload.user;
        this.error = null;
        localStorage.setItem('token', this.token);
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
        useRealtimeStore().connect(this.token, this.user?.id, this.user?.role);
      } catch (err) {
        this.error = err.response?.data?.message || 'Registration failed';
      }
    },
    async logout() {
      if (this.loggingOut) return;
      this.loggingOut = true;

      const token = this.token;
      this.user = null;
      this.token = null;
      this.error = null;
      this.brokenCredentials = [];
      localStorage.removeItem('token');
      delete axios.defaults.headers.common['Authorization'];
      useRealtimeStore().disconnect();

      try {
        if (token) {
          await axios.post('/api/logout', {}, {
            headers: { Authorization: `Bearer ${token}` },
            skipAuthRedirect: true,
            skipErrorToast: true,
          });
        }
      } catch {
        // Ignore server-side logout errors; local session is already cleared.
      } finally {
        this.loggingOut = false;
      }
    },
    async fetchUser() {
      if (!this.token) return;
      try {
        axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
        const response = await axios.get('/api/auth/me');
        this.user = response.data.data || response.data;
      } catch {
        if (this.token) {
          await this.logout();
        }
      }
    },
    async refreshToken() {
      if (!this.token) return;
      const { data } = await axios.post('/api/auth/refresh');
      const payload = data.data || data;
      this.token = payload.token;
      this.user = payload.user;
      localStorage.setItem('token', this.token);
      axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
      if (this.user?.id) {
        useRealtimeStore().connect(this.token, this.user.id, this.user?.role);
      }
    },
    async fetchSyncSummary() {
      try {
        const { data } = await axios.get('/api/user/sync-summary');
        const toast = useToastStore();
        this.syncSummary = {
          new_post_count: Number(data?.new_post_count || 0),
          scheduler_synced_post_count: Number(data?.scheduler_synced_post_count || 0),
          scheduler_unread_count: Number(data?.scheduler_unread_count || 0),
          broken_credentials: data?.broken_credentials || [],
          sync_notifications_seen_at: data?.sync_notifications_seen_at || null,
        };

        if (data.new_post_count > 0) {
          const msg = `${data.new_post_count} new post${data.new_post_count !== 1 ? 's' : ''} synced since your last login.`;
          toast.info(msg);
        }

        this.brokenCredentials = data.broken_credentials || [];
        this.syncSummary.broken_credentials = this.brokenCredentials;

        if (this.brokenCredentials.length > 0) {
          const names = this.brokenCredentials.map(c => c.account_label || c.account_id || c.provider).join(', ');
          const msg = `${this.brokenCredentials.length} account${this.brokenCredentials.length !== 1 ? 's' : ''} need reconnection: ${names}. Visit Credentials.`;
          toast.error(msg, 0);
        }
      } catch {
        // Fail silently — login already succeeded
      }
    },
    async acknowledgeSyncNotifications() {
      try {
        await axios.post('/api/user/sync-summary/acknowledge');
        this.syncSummary.scheduler_unread_count = 0;
        this.syncSummary.sync_notifications_seen_at = new Date().toISOString();
      } catch {
        // Ignore: this is best-effort UX behavior.
      }
    },
  },
});
