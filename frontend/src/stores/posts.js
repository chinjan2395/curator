import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';

function normalizePost(post, feedId = null) {
  return {
    ...post,
    _feedId: post?._feedId ?? post?.feed_id ?? feedId ?? null,
  };
}

function sortPosts(posts) {
  return [...posts].sort((a, b) => {
    if (Boolean(b.pinned) !== Boolean(a.pinned)) {
      return Number(b.pinned) - Number(a.pinned);
    }
    return new Date(b.posted_at) - new Date(a.posted_at);
  });
}

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
    async fetchWorkspace(workspaceId, { feedId = null, since = null, silent = false } = {}) {
      if (!silent) {
        this.loading = true;
        this.error = null;
      }
      try {
        const params = new URLSearchParams();
        if (feedId) params.set('feed_id', String(feedId));
        if (since) params.set('since', since);
        const qs = params.toString() ? `?${params.toString()}` : '';
        const { data } = await axios.get(
          `/api/workspaces/${workspaceId}/posts${qs}`,
          silent ? { skipErrorToast: true } : {},
        );
        const rows = Array.isArray(data) ? data : data.data;
        return rows.map((post) => normalizePost(post));
      } catch (err) {
        if (!silent) {
          this.error = err.response?.data?.message || 'Failed to load posts';
          useToastStore().error(this.error);
        }
        throw err;
      } finally {
        if (!silent) {
          this.loading = false;
        }
      }
    },
    mergePosts(incoming) {
      if (!incoming.length) return;

      const byId = new Map(this.list.map((post) => [Number(post.id), post]));
      for (const post of incoming) {
        const id = Number(post.id);
        const existing = byId.get(id);
        byId.set(id, normalizePost({ ...existing, ...post }, existing?._feedId ?? post.feed_id));
      }
      this.list = sortPosts(Array.from(byId.values()));
    },
    async update(workspaceId, feedId, postId, patch) {
      this.error = null;
      const index = this.list.findIndex((post) => post.id === postId);
      const previous = index !== -1 ? { ...this.list[index] } : null;

      if (index !== -1) {
        this.list[index] = { ...this.list[index], ...patch };
      }

      try {
        const { data } = await axios.put(
          `/api/workspaces/${workspaceId}/feeds/${feedId}/posts/${postId}`,
          patch,
        );
        const body = data?.data ?? data;
        const normalized = normalizePost(
          {
            ...previous,
            ...body,
          },
          body?._feedId ?? body?.feed_id ?? previous?._feedId ?? feedId,
        );
        if (index !== -1) this.list[index] = normalized;
        return normalized;
      } catch (err) {
        if (index !== -1 && previous) {
          this.list[index] = previous;
        }
        const msg = err.response?.data?.message || 'Failed to update post';
        useToastStore().error(msg);
        throw err;
      }
    },
    async bulkUpdate(workspaceId, postIds, patch) {
      this.error = null;
      const ids = new Set(postIds.map((id) => Number(id)));
      const previousById = new Map(
        this.list
          .filter((post) => ids.has(Number(post.id)))
          .map((post) => [Number(post.id), { ...post }]),
      );

      this.list = this.list.map((post) => (
        ids.has(Number(post.id)) ? { ...post, ...patch } : post
      ));

      try {
        const { data } = await axios.put(`/api/workspaces/${workspaceId}/posts/bulk`, {
          post_ids: postIds,
          ...patch,
        });
        const body = data?.data ?? data;
        const updatedPosts = Array.isArray(body) ? body : body?.data ?? [];
        const updatedById = new Map(updatedPosts.map((post) => [Number(post.id), post]));

        this.list = this.list.map((post) => {
          const updated = updatedById.get(Number(post.id));
          if (!updated) return post;

          return normalizePost(
            {
              ...post,
              ...updated,
            },
            updated?._feedId ?? updated?.feed_id ?? post?._feedId ?? post?.feed_id,
          );
        });

        return updatedPosts;
      } catch (err) {
        this.list = this.list.map((post) => {
          const previous = previousById.get(Number(post.id));
          return previous ?? post;
        });
        const msg = err.response?.data?.message || 'Failed to update posts';
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
