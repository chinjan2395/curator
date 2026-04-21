import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

export const usePublishStore = defineStore('publish', {
  state: () => ({
    loading: false,
    publishing: false,
    savingSettings: false,
    error: null,
    stats: null,
    publishSettings: null,
    code: null,
  }),
  actions: {
    async fetchStats(workspaceId) {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get(`/api/workspaces/${workspaceId}/publish/stats`);
        this.stats = data;
        this.publishSettings = data.publish_settings ?? null;
        return data;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to load publish stats';
        this.error = msg;
        useToastStore().error(msg);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async publish(workspaceId) {
      this.publishing = true;
      this.error = null;
      try {
        const { data } = await axios.post(`/api/workspaces/${workspaceId}/publish`);
        useToastStore().success(`Published ${data.published} posts`);
        await this.fetchStats(workspaceId);
        return data;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to publish';
        this.error = msg;
        useToastStore().error(msg);
        throw err;
      } finally {
        this.publishing = false;
      }
    },
    async savePublishSettings(workspaceId, publishSettings) {
      this.savingSettings = true;
      this.error = null;
      try {
        const { data } = await axios.put(
          `/api/workspaces/${workspaceId}/publish/settings`,
          { publish_settings: publishSettings },
        );
        this.publishSettings = data.publish_settings;
        useToastStore().success('Feed appearance saved');
        return data.publish_settings;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to save settings';
        this.error = msg;
        useToastStore().error(msg);
        throw err;
      } finally {
        this.savingSettings = false;
      }
    },
    async fetchCode(workspaceId) {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get(`/api/workspaces/${workspaceId}/publish/code`);
        this.code = data;
        return data;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to load embed code';
        this.error = msg;
        useToastStore().error(msg);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    clear() {
      this.error = null;
      this.stats = null;
      this.publishSettings = null;
      this.code = null;
    },
  },
});

