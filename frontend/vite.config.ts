import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

// The frontend build output must land in ../backend/public/build so that
// Symfony's SpaController can serve it in production (see backend/src/Controller/SpaController.php).
// In dev, Vite serves the SPA on port 5173 and proxies /api to the Symfony dev server on 8000.
export default defineConfig({
  plugins: [react()],
  base: '/build/',
  build: {
    outDir: '../backend/public/build',
    emptyOutDir: true,
    manifest: true,
  },
  server: {
    port: 5173,
    proxy: {
      '/api': 'http://127.0.0.1:8000',
    },
  },
});
