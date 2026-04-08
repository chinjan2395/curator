import { defineStore } from 'pinia';

const STORAGE_KEY = 'curator_current_workspace_id';

export const useAppStore = defineStore('app', {
  state: () => ({
    currentWorkspaceId: (() => {
      try {
        const v = localStorage.getItem(STORAGE_KEY);
        return v ? Number(v) : null;
      } catch {
        return null;
      }
    })(),
  }),
  getters: {
    currentWorkspace(state) {
      return state.currentWorkspaceId;
    },
  },
  actions: {
    setCurrentWorkspace(id) {
      this.currentWorkspaceId = id ? Number(id) : null;
      try {
        if (this.currentWorkspaceId) {
          localStorage.setItem(STORAGE_KEY, String(this.currentWorkspaceId));
        } else {
          localStorage.removeItem(STORAGE_KEY);
        }
      } catch {}
    },
  },
});
