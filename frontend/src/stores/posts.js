import { defineStore } from 'pinia';
import axios from 'axios';
import { useToastStore } from './toast';
import { hydrateFromSession, invalidate, isFresh, persistToSession } from '../utils/sessionCache';

const POSTS_TTL_MS = 2 * 60 * 1000;

function postsCacheKey(workspaceId) {
  return `workspace-posts:${workspaceId}`;
}

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

function mergeById(existing, incoming) {
  const byId = new Map(existing.map((post) => [Number(post.id), normalizePost(post)]));
  for (const post of incoming) {
    const id = Number(post.id);
    const current = byId.get(id);
    byId.set(id, normalizePost({ ...current, ...post }, current?._feedId ?? post.feed_id));
  }
  return sortPosts(Array.from(byId.values()));
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
    filterForFeed(posts, feedId = null) {
      if (!feedId) return sortPosts(posts.map((post) => normalizePost(post)));
      return sortPosts(
        posts
          .map((post) => normalizePost(post))
          .filter((post) => Number(post._feedId ?? post.feed_id) === Number(feedId)),
      );
    },
    getWorkspaceCachedPosts(workspaceId) {
      const cached = hydrateFromSession(postsCacheKey(workspaceId));
      return cached?.value ?? [];
    },
    replaceWorkspaceCache(workspaceId, posts) {
      persistToSession(postsCacheKey(workspaceId), sortPosts(posts.map((post) => normalizePost(post))));
    },
    mergeIntoWorkspaceCache(workspaceId, incoming) {
      if (!incoming.length) return;
      const next = mergeById(this.getWorkspaceCachedPosts(workspaceId), incoming);
      this.replaceWorkspaceCache(workspaceId, next);
    },
    applyVisiblePosts(workspaceId, feedId = null) {
      this.list = this.filterForFeed(this.getWorkspaceCachedPosts(workspaceId), feedId);
      return this.list;
    },
    async fetchWorkspace(workspaceId, { feedId = null, since = null, silent = false, force = false, background = true } = {}) {
      const cacheKey = postsCacheKey(workspaceId);
      const shouldUseCache = !since;
      const cached = shouldUseCache ? hydrateFromSession(cacheKey) : null;

      if (cached) {
        this.list = this.filterForFeed(cached.value, feedId);
      }

      if (!force && !since && cached && isFresh(cached, POSTS_TTL_MS)) {
        return this.filterForFeed(cached.value, feedId);
      }

      if (!force && !since && cached && background) {
        this.revalidateWorkspace(workspaceId, { feedId, silent: true }).catch(() => {});
        return this.filterForFeed(cached.value, feedId);
      }

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
        const rows = (Array.isArray(data) ? data : data.data).map((post) => normalizePost(post));

        if (since) {
          this.mergeIntoWorkspaceCache(workspaceId, rows);
        } else {
          this.replaceWorkspaceCache(workspaceId, rows);
        }

        const visible = this.applyVisiblePosts(workspaceId, feedId);
        return since ? rows : visible;
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
    async revalidateWorkspace(workspaceId, { feedId = null, since = null, silent = false } = {}) {
      return this.fetchWorkspace(workspaceId, { feedId, since, silent, force: true, background: false });
    },
    mergePosts(incoming, workspaceId = null, feedId = null) {
      if (!incoming.length) return;
      this.list = mergeById(this.list, incoming);

      if (workspaceId) {
        this.mergeIntoWorkspaceCache(workspaceId, incoming);
        if (feedId != null) {
          this.applyVisiblePosts(workspaceId, feedId);
        }
      }
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
        this.mergeIntoWorkspaceCache(workspaceId, [normalized]);
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

        this.mergeIntoWorkspaceCache(workspaceId, this.list.filter((post) => ids.has(Number(post.id))));
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
        this.replaceWorkspaceCache(
          workspaceId,
          this.getWorkspaceCachedPosts(workspaceId).filter((post) => Number(post.id) !== Number(postId)),
        );
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
    invalidateWorkspace(workspaceId) {
      invalidate(postsCacheKey(workspaceId));
    },
  },
});
