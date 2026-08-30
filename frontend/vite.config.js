import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

// https://vite.dev/config/
export default defineConfig({
  // Relative build URLs allow the same files to run at / or any subdirectory.
  base: './',
  plugins: [react()],
  build: {
    // The repository root is also the production web document root.
    outDir: '..',
    emptyOutDir: false,
  },
})
