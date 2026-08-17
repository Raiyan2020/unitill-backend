import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  // Deployed to the root of its own dedicated dashboard domain (not nested under
  // the Laravel app's /admin/* path anymore). Root-absolute so asset URLs still
  // resolve correctly no matter which client-side route (e.g. /users/42) the
  // browser is currently on when index.html loads.
  base: '/',
  build: {
    outDir: '../public/dist',
    emptyOutDir: true,
  },
  server: {
    port: 5173,
    proxy: {
      '/api': {
        target: 'http://127.0.0.1:8000',
        changeOrigin: true,
      },
    },
  },
});
