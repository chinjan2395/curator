import { ref } from 'vue';
import axios from 'axios';

export function useAdminSystemOverview() {
  const overview = ref(null);
  const health = ref(null);
  const loading = ref(true);
  const error = ref(null);

  async function fetchOverview() {
    loading.value = true;
    error.value = null;
    try {
      const [o, h] = await Promise.all([
        axios.get('/api/admin/system/overview', { skipErrorToast: true }),
        axios.get('/api/admin/integrations/health', { skipErrorToast: true }),
      ]);
      overview.value = o.data.data || o.data;
      health.value = h.data.data || h.data;
    } catch (e) {
      error.value = e.response?.data?.message || 'Failed to load system overview';
    } finally {
      loading.value = false;
    }
  }

  return {
    overview,
    health,
    loading,
    error,
    fetchOverview,
  };
}
