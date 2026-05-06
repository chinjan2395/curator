import { defineStore } from 'pinia';
import axios from 'axios';

export const useUsersStore = defineStore('users', {
  state: () => ({
    list: [],
    pagination: null,
    currentUser: null,
    loading: false,
    error: null,
  }),

  actions: {
    async fetchAll(params = {}) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get('/api/admin/users', { params });
        this.list = response.data.data;
        this.pagination = {
          current_page: response.data.current_page,
          last_page: response.data.last_page,
          per_page: response.data.per_page,
          total: response.data.total,
          from: response.data.from,
          to: response.data.to,
        };
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load users.';
      } finally {
        this.loading = false;
      }
    },

    async fetchOne(id) {
      this.loading = true;
      this.error = null;
      try {
        const response = await axios.get(`/api/admin/users/${id}`);
        this.currentUser = response.data;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load user.';
      } finally {
        this.loading = false;
      }
    },

    async updateUser(id, data) {
      try {
        const response = await axios.put(`/api/admin/users/${id}`, data);
        const updated = response.data.user;
        this.currentUser = updated;
        const idx = this.list.findIndex((u) => u.id === id);
        if (idx !== -1) this.list[idx] = { ...this.list[idx], ...updated };
        return { success: true };
      } catch (err) {
        return { success: false, message: err.response?.data?.message || 'Update failed.' };
      }
    },

    async deleteUser(id) {
      try {
        await axios.delete(`/api/admin/users/${id}`);
        this.list = this.list.filter((u) => u.id !== id);
        if (this.currentUser?.id === id) this.currentUser = null;
        return { success: true };
      } catch (err) {
        return { success: false, message: err.response?.data?.message || 'Delete failed.' };
      }
    },

    async resetPassword(id) {
      try {
        const response = await axios.post(`/api/admin/users/${id}/reset-password`);
        return { success: true, message: response.data.message };
      } catch (err) {
        return { success: false, message: err.response?.data?.message || 'Failed to send reset link.' };
      }
    },

    async deactivate(id) {
      try {
        const response = await axios.post(`/api/admin/users/${id}/deactivate`);
        const updated = response.data.user;
        this._applyUpdate(id, updated);
        return { success: true, message: response.data.message };
      } catch (err) {
        return { success: false, message: err.response?.data?.message || 'Failed to deactivate user.' };
      }
    },

    async activate(id) {
      try {
        const response = await axios.post(`/api/admin/users/${id}/activate`);
        const updated = response.data.user;
        this._applyUpdate(id, updated);
        return { success: true, message: response.data.message };
      } catch (err) {
        return { success: false, message: err.response?.data?.message || 'Failed to activate user.' };
      }
    },

    _applyUpdate(id, updated) {
      if (this.currentUser?.id === id) this.currentUser = { ...this.currentUser, ...updated };
      const idx = this.list.findIndex((u) => u.id === id);
      if (idx !== -1) this.list[idx] = { ...this.list[idx], ...updated };
    },
  },
});
