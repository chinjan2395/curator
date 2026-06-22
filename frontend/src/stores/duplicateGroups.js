import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { usePostsStore } from './posts';
import { hydrateFromSession, invalidate, isFresh, persistToSession, withDedupe } from '../utils/sessionCache';

const DUPLICATE_GROUPS_TTL_MS = 2 * 60 * 1000;

function duplicateGroupsCacheKey(workspaceId) {
  return `duplicate-groups:${workspaceId}`;
}

export const useDuplicateGroupsStore = defineStore('duplicateGroups', () => {
  const list = ref([]);
  const loading = ref(false);
  const error = ref(null);

  const duplicatedPostIds = computed(() => {
    const ids = new Set();
    for (const group of list.value) {
      if (group.status === 'open') {
        for (const post of group.posts ?? []) {
          ids.add(post.id);
        }
      }
    }
    return ids;
  });

  async function fetch(workspaceId, { force = false, background = true } = {}) {
    const cacheKey = duplicateGroupsCacheKey(workspaceId);
    const cached = hydrateFromSession(cacheKey);

    if (cached) {
      list.value = cached.value;
    }

    if (!force && cached && isFresh(cached, DUPLICATE_GROUPS_TTL_MS)) {
      return cached.value;
    }

    if (!force && cached && background) {
      revalidate(workspaceId).catch(() => {});
      return cached.value;
    }

    return revalidate(workspaceId);
  }

  async function revalidate(workspaceId) {
    const cacheKey = duplicateGroupsCacheKey(workspaceId);
    loading.value = true;
    error.value = null;
    try {
      const groups = await withDedupe(cacheKey, async () => {
        const res = await window.axios.get(`/api/workspaces/${workspaceId}/duplicate-groups`);
        const next = res.data?.data ?? res.data ?? [];
        persistToSession(cacheKey, next);
        return next;
      });
      list.value = groups;
      return groups;
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Failed to load duplicate groups.';
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function scan(workspaceId) {
    loading.value = true;
    error.value = null;
    try {
      await window.axios.post(`/api/workspaces/${workspaceId}/duplicate-groups/scan`);
      invalidate(duplicateGroupsCacheKey(workspaceId));
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Scan failed.';
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function keepPost(workspaceId, groupId, postId) {
    const group = list.value.find((entry) => entry.id === groupId);
    await window.axios.post(`/api/workspaces/${workspaceId}/duplicate-groups/${groupId}/keep/${postId}`);
    list.value = list.value.filter((g) => g.id !== groupId);
    persistToSession(duplicateGroupsCacheKey(workspaceId), list.value);
    const postsStore = usePostsStore();
    const updates = (group?.posts ?? [])
      .filter((post) => Number(post.id) !== Number(postId))
      .map((post) => ({ ...post, status: 'rejected' }));
    if (updates.length) {
      postsStore.mergePosts(updates, workspaceId);
    }
  }

  async function dismiss(workspaceId, groupId) {
    await window.axios.post(`/api/workspaces/${workspaceId}/duplicate-groups/${groupId}/dismiss`);
    list.value = list.value.filter((g) => g.id !== groupId);
    persistToSession(duplicateGroupsCacheKey(workspaceId), list.value);
  }

  return { list, loading, error, duplicatedPostIds, fetch, scan, keepPost, dismiss };
});
