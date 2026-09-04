import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  base: './',
  build: {
    outDir: path.resolve(__dirname, '../assets/support/admin'),
    emptyOutDir: true,
    rollupOptions: {
      output: {
        entryFileNames: 'support-admin.js',
        chunkFileNames: 'support-admin-[name].js',
        assetFileNames: 'support-admin.[ext]',
      },
    },
  },
});
