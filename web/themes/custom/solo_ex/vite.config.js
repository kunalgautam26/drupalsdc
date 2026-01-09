import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { glob } from 'glob';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  build: {
    manifest: true,
    outDir: 'dist',
    emptyOutDir: true,

    rollupOptions: {
      input: Object.fromEntries(
        glob.sync('components/*/*.{js,jsx}').map(file => {
          const name = path.basename(path.dirname(file));
          return [name, file];
        })
      ),

      output: {
        // dist/component/component.js
        entryFileNames: '[name]/[name]-[hash].js',

        // dist/component/component.css
        assetFileNames: asset => {
          if (asset.name?.endsWith('.css')) {
            return '[name]/[name]-[hash].css';
          }
          return '[name]/assets/[name]-[hash][extname]';
        },
      },
    },
  },
  server: {
    // Required for the Drupal Vite module to proxy correctly
    host: 'localhost',
    port: 5173,
    strictPort: true,
    origin: 'http://localhost:5173', 
  },
});