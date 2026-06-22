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
        this.list = Array.isArray(data) ? data : data.data;
        return this.list;
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
        const feed = data.data ?? data;
        this.list.push(feed);
        useToastStore().success('Feed created');
        return feed;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to create feed';
        this.error = msg;
        this.lastActionError = msg;
        useToastStore().error(msg);
        throw err;
      }
    },
    async patchSyncSettings(workspaceId, feedId, payload) {
      this.error = null;
      this.lastActionError = null;
      try {
        const { data: body } = await axios.patch(
          `/api/workspaces/${workspaceId}/feeds/${feedId}/sync-settings`,
          payload,
        );
        const feedData = body?.data ?? body;
        const i = this.list.findIndex((f) => f.id === feedId);
        if (i !== -1) this.list[i] = { ...this.list[i], ...feedData };
        return feedData;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to update sync settings';
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
        const feed = data.data ?? data;
        const i = this.list.findIndex((f) => f.id === feedId);
        if (i !== -1) this.list[i] = feed;
        useToastStore().success('Feed updated');
        return feed;
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
        const payload = data.data || data;
        const i = this.list.findIndex((f) => f.id === feedId);
        if (i !== -1 && payload.last_synced_at) {
          this.list[i] = { ...this.list[i], last_synced_at: payload.last_synced_at };
        }
        if (!silent) {
          if (payload.queued) {
            useToastStore().info('Feed sync started…');
          } else {
            useToastStore().success(`Synced feed (${payload.created ?? 0} posts)`);
          }
        }
        return payload;
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
