import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';

// When you run `npm run dev` on your machine, the hostname `backend` does not exist
// (it only resolves inside Docker Compose). Default to localhost; override in Docker via env.
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  // Default to local Laravel so /api (including /api/auth/social/providers) hits your .env, not a remote deploy.
  const apiProxyTarget =
    env.VITE_API_PROXY_TARGET || process.env.VITE_API_PROXY_TARGET || 'http://127.0.0.1:8000';

  return {
    plugins: [vue()],
    server: {
      host: '0.0.0.0',
      port: 5173,
      proxy: {
        '/api': {
          target: apiProxyTarget,
          changeOrigin: true,
        },
      },
    },
  };
});
