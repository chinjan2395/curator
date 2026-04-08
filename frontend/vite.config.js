import { defineConfig, loadEnv } from 'vite';
import vue from '@vitejs/plugin-vue';

// When you run `npm run dev` on your machine, the hostname `backend` does not exist
// (it only resolves inside Docker Compose). Default to localhost; override in Docker via env.
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '');
  const apiProxyTarget =
    env.VITE_API_PROXY_TARGET || process.env.VITE_API_PROXY_TARGET || 'powerful-rejoicing-copy-production.up.railway.app';

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
