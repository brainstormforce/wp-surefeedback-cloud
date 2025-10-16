import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { resolve } from 'node:path'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig(({ command }) => ({
  plugins: [react({
    jsxRuntime: 'automatic'
  })],
  define: {
    'process.env.NODE_ENV': JSON.stringify(command === 'serve' ? 'development' : 'production')
  },
  build: {
    watch: null,
    rollupOptions: {
      input: resolve(fileURLToPath(new URL('.', import.meta.url)), 'resources/assets/js/main.jsx'),
      external: [],
      output: {
        format: 'iife',
        entryFileNames: 'admin.js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name === 'tailwind.css') {
            return 'admin.css'
          }
          return '[name].[ext]'
        },
        chunkFileNames: '[name].js'
      }
    },
    outDir: 'assets/dist',
    commonjsOptions: {
      transformMixedEsModules: true
    },
    minify: command === 'serve' ? false : 'esbuild'
  },
  resolve: {
    alias: {
      '@': resolve(fileURLToPath(new URL('.', import.meta.url)), 'src')
    }
  }
}))