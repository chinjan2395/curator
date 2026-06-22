import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

export const useCampaignsStore = defineStore('campaigns', {
  state: () => ({
    list: [],
    current: null,
    loading: false,
    error: null,
  }),
  actions: {
    async fetchAll() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get('/api/campaigns');
        this.list = data.data || data;
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to load campaigns';
      } finally {
        this.loading = false;
      }
    },
    async fetchOne(id) {
      this.error = null;
      try {
        const { data } = await axios.get(`/api/campaigns/${id}`);
        this.current = data.data || data;
        return this.current;
      } catch (e) {
        this.error = e.response?.data?.message || 'Failed to load campaign';
        throw e;
      }
    },
    async create(payload) {
      try {
        const { data } = await axios.post('/api/campaigns', payload);
        useToastStore().success('Campaign created');
        await this.fetchAll();
        return data.data || data;
      } catch (e) {
        throw e;
      }
    },
    async generate(id) {
      try {
        const { data } = await axios.post(`/api/campaigns/${id}/generate`);
        const payload = data.data || data;
        if (payload?.queued) {
          useToastStore().info('Content generation started…');
        } else {
          useToastStore().success('Content generated');
        }
        return payload;
      } catch (e) {
        throw e;
      }
    },
  },
});
