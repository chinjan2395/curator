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
  getters: {
    byProvider: (state) => {
      const grouped = {};
      for (const cred of state.list) {
        if (!grouped[cred.provider]) grouped[cred.provider] = [];
        grouped[cred.provider].push(cred);
      }
      return grouped;
    },
  },
  actions: {
    async fetchAll() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get('/api/social-credentials');
        this.list = Array.isArray(data) ? data : data.data;
        return this.list;
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
    async disconnect(id) {
      try {
        const { data } = await axios.post('/api/social/disconnect', { id });
        useToastStore().success('Disconnected');
        await this.fetchAll();
        return data;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to disconnect';
        useToastStore().error(msg);
        throw err;
      }
    },
    async syncAll() {
      await Promise.allSettled(this.list.map((c) => this.syncCredential(c.id)));
    },
    async syncCredential(id) {
      try {
        const { data } = await axios.post(`/api/social-credentials/${id}/sync`);
        const result = data?.data ?? data;
        const idx = this.list.findIndex((c) => c.id === id);
        if (idx !== -1 && result.status) {
          this.list[idx] = { ...this.list[idx], status: result.status };
        }
        return result;
      } catch (err) {
        const msg = err.response?.data?.message || 'Sync failed';
        useToastStore().error(msg);
        throw err;
      }
    },
    async renameCredential(id, accountLabel) {
      try {
        const { data } = await axios.put(`/api/social-credentials/${id}/label`, { account_label: accountLabel });
        const idx = this.list.findIndex((c) => c.id === id);
        if (idx !== -1) this.list[idx] = data;
        useToastStore().success('Label updated');
        return data;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to rename';
        useToastStore().error(msg);
        throw err;
      }
    },
  },
});
