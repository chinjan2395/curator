import { ref } from 'vue';
import axios from 'axios';

let cached = null;
let loadPromise = null;

export function useCapabilities() {
  const capabilities = ref(cached);
  const loading = ref(false);
  const error = ref(null);

  async function fetchCapabilities(force = false) {
    if (cached && !force) {
      capabilities.value = cached;
      return cached;
    }
    if (loadPromise && !force) {
      return loadPromise;
    }

    loading.value = true;
    error.value = null;
    loadPromise = axios
      .get('/api/capabilities', { skipErrorToast: true })
      .then(({ data }) => {
        cached = data.data || data;
        capabilities.value = cached;
        return cached;
      })
      .catch((e) => {
        error.value = e.response?.data?.message || 'Failed to load capabilities';
        return null;
      })
      .finally(() => {
        loading.value = false;
        loadPromise = null;
      });

    return loadPromise;
  }

  return { capabilities, loading, error, fetchCapabilities };
}
