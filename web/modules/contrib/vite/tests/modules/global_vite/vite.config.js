import { defineConfig } from 'vite';
import fg from 'fast-glob';

export default defineConfig(() => {
  return {
    build: {
      manifest: true,
      rollupOptions: {
        input: fg.sync([
          '**/scss/**/*.scss',
          '!**/scss/**/_*.scss',
          '**/components/**/*.css',
          '**/components/**/*.js',
          '**/js/**/*.js',
        ]),
        output: {
          manualChunks: (id) => {
            if (id.includes('import.mjs')) {
              return 'import'
            }
          },
        },
      },
    },
    server: {
      host: true,
      port: 5173,
      strictPort: true,
    },
  }
})
