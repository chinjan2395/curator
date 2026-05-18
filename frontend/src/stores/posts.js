import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

export const usePostsStore = defineStore('posts', {
  state: () => ({
    list: [],
    loading: false,
    error: null,
  }),
  actions: {
    async fetchAll(workspaceId, feedId, { status = null } = {}) {
      this.loading = true;
      this.error = null;
      try {
        const qs = status ? `?status=${encodeURIComponent(status)}` : '';
        const { data } = await axios.get(`/api/workspaces/${workspaceId}/feeds/${feedId}/posts${qs}`);
        this.list = Array.isArray(data) ? data : data.data;
        return this.list;
      } catch (err) {
        this.error = err.response?.data?.message || 'Failed to load posts';
        useToastStore().error(this.error);
        throw err;
      } finally {
        this.loading = false;
      }
    },
    async update(workspaceId, feedId, postId, patch) {
      this.error = null;
      try {
        const { data } = await axios.put(
          `/api/workspaces/${workspaceId}/feeds/${feedId}/posts/${postId}`,
          patch,
        );
        const body = data?.data ?? data;
        const i = this.list.findIndex((p) => p.id === postId);
        const previous = i !== -1 ? this.list[i] : null;
        const normalized = {
          ...previous,
          ...body,
          // Preserve local link used by Curate grouping when API payload omits it.
          _feedId: body?._feedId ?? body?.feed_id ?? previous?._feedId ?? feedId,
        };
        if (i !== -1) this.list[i] = normalized;
        return normalized;
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to update post';
        useToastStore().error(msg);
        throw err;
      }
    },
    async remove(workspaceId, feedId, postId) {
      this.error = null;
      try {
        await axios.delete(`/api/workspaces/${workspaceId}/feeds/${feedId}/posts/${postId}`);
        this.list = this.list.filter((p) => p.id !== postId);
        useToastStore().success('Post deleted');
      } catch (err) {
        const msg = err.response?.data?.message || 'Failed to delete post';
        useToastStore().error(msg);
        throw err;
      }
    },
    clearList() {
      this.list = [];
      this.error = null;
    },
  },
});

