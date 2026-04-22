import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

export const useFeedsStore = defineStore('feeds', {
  state: () => ({
    list: [],
    loading: false,
    error: null,
    syncing: false,
    lastActionError: null,
  }),
  actions: {
    async fetchAll(workspaceId) {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get(`/api/workspaces/${workspaceId}/feeds`);
        this.list = data;
        return data;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load feeds';
        useToastStore().error(this.error);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async create(workspaceId, payload) {
      this.error = null;
      this.lastActionError = null;
      try {
        const { data } = await axios.post(`/api/workspaces/${workspaceId}/feeds`, payload);
        this.list.push(data);
        useToastStore().success('Feed created');
        return data;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to create feed';
        this.error = msg;
        this.lastActionError = msg;
        useToastStore().error(msg);
        throw err;
      }
    },
    async update(workspaceId, feedId, payload) {
      this.error = null;
      this.lastActionError = null;
      try {
        const { data } = await axios.put(`/api/workspaces/${workspaceId}/feeds/${feedId}`, payload);
        const i = this.list.findIndex((f) => f.id === feedId);
        if (i !== -1) this.list[i] = data;
        useToastStore().success('Feed updated');
        return data;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to update feed';
        this.error = msg;
        this.lastActionError = msg;
        useToastStore().error(msg);
        throw err;
      }
    },
    async remove(workspaceId, feedId) {
      this.error = null;
      this.lastActionError = null;
      try {
        await axios.delete(`/api/workspaces/${workspaceId}/feeds/${feedId}`);
        this.list = this.list.filter((f) => f.id !== feedId);
        useToastStore().success('Feed deleted');
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to delete feed';
        this.error = msg;
        this.lastActionError = msg;
        useToastStore().error(msg);
        throw err;
      }
    },
    async sync(workspaceId, feedId, { count = 8, silent = false } = {}) {
      this.error = null;
      this.syncing = true;
      try {
        const { data } = await axios.post(`/api/workspaces/${workspaceId}/feeds/${feedId}/sync`, { count });
        const i = this.list.findIndex((f) => f.id === feedId);
        if (i !== -1) {
          this.list[i] = { ...this.list[i], last_synced_at: data.last_synced_at };
        }
        if (!silent) useToastStore().success(`Synced feed (${data.created} posts)`);
        return data;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to sync feed';
        useToastStore().error(msg);
        throw err;
      } finally {
        this.syncing = false;
      }
    },
    clearList() {
      this.list = [];
      this.error = null;
    },
  },
});
