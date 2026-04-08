import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

export const useCredentialsStore = defineStore('credentials', {
  state: () => ({
    list: [],
    loading: false,
    error: null,
    connecting: false,
  }),
  actions: {
    async fetchAll() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get('/api/social-credentials');
        this.list = data;
        return data;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load credentials';
        useToastStore().error(this.error);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async connect(provider) {
      this.connecting = true;
      try {
        const { data } = await axios.post('/api/social/connect', { provider });
        if (data.auth_url) {
          window.location.href = data.auth_url;
          return data;
        }
        return data;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to start connection';
        useToastStore().error(msg);
        throw err;
      } finally {
        this.connecting = false;
      }
    },
    async disconnect(provider) {
      try {
        const { data } = await axios.post('/api/social/disconnect', { provider });
        useToastStore().success('Disconnected');
        await this.fetchAll();
        return data;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to disconnect';
        useToastStore().error(msg);
        throw err;
      }
    },
  },
});

