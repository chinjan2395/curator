import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { usePostsStore } from './posts';

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

  async function fetch(workspaceId) {
    loading.value = true;
    error.value = null;
    try {
      const res = await window.axios.get(`/api/workspaces/${workspaceId}/duplicate-groups`);
      list.value = res.data?.data ?? res.data ?? [];
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Failed to load duplicate groups.';
    } finally {
      loading.value = false;
    }
  }

  async function scan(workspaceId) {
    loading.value = true;
    error.value = null;
    try {
      await window.axios.post(`/api/workspaces/${workspaceId}/duplicate-groups/scan`);
    } catch (e) {
      error.value = e?.response?.data?.message ?? 'Scan failed.';
      throw e;
    } finally {
      loading.value = false;
    }
  }

  async function keepPost(workspaceId, groupId, postId) {
    try {
      await window.axios.post(`/api/workspaces/${workspaceId}/duplicate-groups/${groupId}/keep/${postId}`);
      list.value = list.value.filter((g) => g.id !== groupId);
      // Refresh posts so rejected ones update in the grid
      const postsStore = usePostsStore();
      const res = await window.axios.get(`/api/workspaces/${workspaceId}/posts`);
      postsStore.list = res.data?.data ?? res.data ?? postsStore.list;
    } catch (e) {
      // Re-throw so the component can show a toast
      throw e;
    }
  }

  async function dismiss(workspaceId, groupId) {
    try {
      await window.axios.post(`/api/workspaces/${workspaceId}/duplicate-groups/${groupId}/dismiss`);
      list.value = list.value.filter((g) => g.id !== groupId);
    } catch (e) {
      throw e;
    }
  }

  return { list, loading, error, duplicatedPostIds, fetch, scan, keepPost, dismiss };
});
