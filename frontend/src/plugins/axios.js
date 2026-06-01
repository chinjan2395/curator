import axios from 'axios';
import router from '../router/index.js';
import { useAuthStore } from '../stores/auth';
import { useToastStore } from '../stores/toast';

const AUTH_PATHS = ['/api/login', '/api/register', '/api/auth/login', '/api/auth/register'];

function isAuthRequest(url = '') {
  return AUTH_PATHS.some((path) => String(url).includes(path));
}

function errorMessage(error) {
  const data = error.response?.data;
  if (data?.message) return data.message;
  if (data?.errors && typeof data.errors === 'object') {
    const first = Object.values(data.errors).flat()[0];
    if (first) return String(first);
  }
  return error.message || 'Something went wrong. Please try again.';
}

export function setupAxiosInterceptors() {
  axios.interceptors.response.use(
    (response) => response,
    async (error) => {
      const config = error.config || {};
      const status = error.response?.status;
      const url = config.url || '';

      if (status === 401 && !isAuthRequest(url)) {
        const auth = useAuthStore();
        await auth.logout();
        if (router.currentRoute.value.path !== '/login') {
          router.push('/login');
        }
        if (!config.skipErrorToast) {
          useToastStore().error('Your session has expired. Please sign in again.');
        }
        return Promise.reject(error);
      }

      if (!config.skipErrorToast && status && status >= 400 && !axios.isCancel(error)) {
        useToastStore().error(errorMessage(error));
      }

      return Promise.reject(error);
    },
  );
}
