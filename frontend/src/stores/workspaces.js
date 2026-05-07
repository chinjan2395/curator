import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

export const useWorkspacesStore = defineStore('workspaces', {
  state: () => ({
    list: [],
    loading: false,
    error: null,
  }),
  actions: {
    async fetchAll() {
      this.loading = true;
      this.error = null;
      try {
        const { data } = await axios.get('/api/workspaces');
        this.list = data.data;
        return data.data;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load workspaces';
        useToastStore().error(this.error);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async create(name) {
      this.error = null;
      try {
        const { data } = await axios.post('/api/workspaces', { name });
        this.list.push(data);
        useToastStore().success('Workspace created');
        return data;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to create workspace';
        useToastStore().error(this.error);
        throw err;
      }
    },
    async update(id, name) {
      this.error = null;
      try {
        const { data } = await axios.put(`/api/workspaces/${id}`, { name });
        const i = this.list.findIndex((w) => w.id === id);
        if (i !== -1) this.list[i] = data;
        useToastStore().success('Workspace updated');
        return data;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to update workspace';
        useToastStore().error(this.error);
        throw err;
      }
    },
    async remove(id) {
      this.error = null;
      try {
        await axios.delete(`/api/workspaces/${id}`);
        this.list = this.list.filter((w) => w.id !== id);
        useToastStore().success('Workspace deleted');
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to delete workspace';
        useToastStore().error(msg);
        throw err;
      }
    },
  },
});
