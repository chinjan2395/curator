import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';
import { hydrateFromSession, invalidate, isFresh, persistToSession, withDedupe } from '../utils/sessionCache';

const GDRIVE_TTL_MS = 30 * 60 * 1000;
const CACHE_KEY = 'google-drive';

export const useGoogleDriveStore = defineStore('googleDrive', {
  state: () => ({
    status: null,
    loading: false,
    connecting: false,
    error: null,
  }),
  getters: {
    isConnected: (state) => Boolean(state.status?.connected),
    needsReconnect: (state) => state.status?.token_health === 'needs_reauth',
  },
  actions: {
    async fetchStatus({ force = false, background = true } = {}) {
      const cached = hydrateFromSession(CACHE_KEY);

      if (cached) {
        this.status = cached.value;
      }

      if (!force && cached && isFresh(cached, GDRIVE_TTL_MS)) {
        return cached.value;
      }

      if (!force && cached && background) {
        this.revalidate().catch(() => {});
        return cached.value;
      }

      return this.revalidate();
    },
    async revalidate() {
      this.loading = true;
      this.error = null;
      try {
        const status = await withDedupe(CACHE_KEY, async () => {
          const { data } = await axios.get('/api/google-drive');
          const next = data?.data ?? data;
          persistToSession(CACHE_KEY, next);
          return next;
        });
        this.status = status;
        return status;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load Google Drive status';
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async connect() {
      this.connecting = true;
      try {
        const { data } = await axios.post('/api/google-drive/connect');
        const authUrl = data?.data?.auth_url ?? data?.auth_url;
        if (authUrl) {
          window.location.href = authUrl;
          return data;
        }
        throw new Error('No authorization URL returned');
      } catch (err) {
        const msg = err.response?.data?.message || err.message || 'Failed to start Google Drive connection';
        useToastStore().error(msg);
        throw err;
      } finally {
        this.connecting = false;
      }
    },
    async disconnect() {
      try {
        await axios.delete('/api/google-drive');
        useToastStore().success('Google Drive disconnected');
        await this.fetchStatus({ force: true });
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to disconnect Google Drive';
        useToastStore().error(msg);
        throw err;
      }
    },
    invalidateCache() {
      invalidate(CACHE_KEY);
    },
  },
});
