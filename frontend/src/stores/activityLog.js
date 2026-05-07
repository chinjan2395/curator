import { defineStore } from 'pinia'
import axios from 'axios'

const STORAGE_KEY = 'curator_activity_panel'

export const useActivityLogStore = defineStore('activityLog', {
  state: () => ({
    logs: [],
    loading: false,
    panelOpen: localStorage.getItem(STORAGE_KEY) === 'true',
    adminLogs: [],
    adminMeta: null,
    adminLoading: false,
  }),

  actions: {
    async fetchMyLogs() {
      this.loading = true
      try {
        const { data } = await axios.get('/api/activity-logs')
        this.logs = data
      } catch {
        // silently ignore
      } finally {
        this.loading = false
      }
    },

    togglePanel() {
      this.panelOpen = !this.panelOpen
      localStorage.setItem(STORAGE_KEY, this.panelOpen)
      if (this.panelOpen) {
        this.fetchMyLogs()
      }
    },

    async fetchAdminLogs(params = {}) {
      this.adminLoading = true
      try {
        const { data } = await axios.get('/api/admin/activity-logs', { params })
        this.adminLogs = data.data
        this.adminMeta = data.meta
      } catch {
        // silently ignore
      } finally {
        this.adminLoading = false
      }
    },
  },
})
