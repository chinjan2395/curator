import { defineStore } from 'pinia';
import axios from 'axios';

export const useSyncOpsStore = defineStore('syncOps', {
  state: () => ({
    status: null,
    logs: [],
    logsPagination: null,
    brokenCredentials: [],
    loading: { status: false, logs: false, broken: false, runAll: false },
    error: null,
    logFilters: { provider: '', status: '', triggered_by: '' },
    logsPage: 1,
    actionLoading: {},
    runAllProgress: null,
  }),
  actions: {
    async fetchStatus() {
      this.loading.status = true;
      try {
        const { data } = await axios.get('/api/admin/sync/status');
        this.status = data;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load status';
      } finally {
        this.loading.status = false;
      }
    },

    async fetchLogs(page = 1) {
      this.logsPage = page;
      this.loading.logs = true;
      try {
        const params = { page };
        if (this.logFilters.provider) params.provider = this.logFilters.provider;
        if (this.logFilters.status) params.status = this.logFilters.status;
        if (this.logFilters.triggered_by) params.triggered_by = this.logFilters.triggered_by;
        const { data } = await axios.get('/api/admin/sync/logs', { params });
        this.logs = data.data;
        this.logsPagination = {
          total: data.total,
          from: data.from,
          to: data.to,
          last_page: data.last_page,
          current_page: data.current_page,
        };
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load logs';
      } finally {
        this.loading.logs = false;
      }
    },

    async fetchBrokenCredentials() {
      this.loading.broken = true;
      try {
        const { data } = await axios.get('/api/admin/sync/broken-credentials');
        this.brokenCredentials = data;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load broken credentials';
      } finally {
        this.loading.broken = false;
      }
    },

    async runAll() {
      this.loading.runAll = true;
      this.runAllProgress = null;
      try {
        await axios.post('/api/admin/sync/run-all');
        return { success: true, queued: true };
      } catch (err) {
        return { success: false, message: err.response?.data?.message || 'Failed to start sync' };
      } finally {
        this.loading.runAll = false;
      }
    },

    async resyncCredential(credentialId) {
      this.actionLoading[credentialId] = true;
      try {
        const { data } = await axios.post(`/api/admin/sync/credentials/${credentialId}/resync`);
        return { success: true, queued: true, data };
      } catch (err) {
        return { success: false, message: err.response?.data?.message || 'Resync failed' };
      } finally {
        delete this.actionLoading[credentialId];
      }
    },

    async syncFeed(feedId) {
      this.actionLoading[`feed_${feedId}`] = true;
      try {
        await axios.post(`/api/admin/sync/feeds/${feedId}`);
        return { success: true, queued: true };
      } catch (err) {
        return { success: false, message: err.response?.data?.message || 'Sync failed' };
      } finally {
        delete this.actionLoading[`feed_${feedId}`];
      }
    },

    handleAdminSyncEvent(event) {
      if (event.job_type === 'run_all' && event.status === 'progress') {
        this.runAllProgress = event.data;
      }
      if (event.status === 'completed' || event.status === 'failed') {
        this.runAllProgress = null;
        this.fetchStatus();
        this.fetchLogs(1);
        if (event.job_type === 'resync_credential') {
          this.fetchBrokenCredentials();
        }
      }
    },
  },
});
