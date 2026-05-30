import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { fileURLToPath, URL } from 'node:url'

// The dev server proxies every `/api` request to the backend so the SPA talks
// to the API same-origin (no CORS, no preflight). The target is environment
// driven: inside the compose network the backend answers on `http://backend:80`,
// which is the default; set VITE_PROXY_TARGET to point at any other instance
// (e.g. `http://localhost:8001` when running Vite straight on the host).
const proxyTarget = process.env.VITE_PROXY_TARGET ?? 'http://backend:80'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [vue(), tailwindcss()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    host: true,
    port: 5173,
    proxy: {
      '/api': {
        target: proxyTarget,
        changeOrigin: true,
      },
    },
  },
  build: {
    target: 'esnext',
    sourcemap: true,
  },
})
