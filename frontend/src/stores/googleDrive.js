import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

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
    async fetchStatus() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get('/api/google-drive');
        this.status = data?.data ?? data;
        return this.status;
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
        await this.fetchStatus();
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to disconnect Google Drive';
        useToastStore().error(msg);
        throw err;
      }
    },
  },
});
