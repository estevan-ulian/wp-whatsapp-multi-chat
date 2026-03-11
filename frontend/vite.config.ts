import { defineConfig } from 'vite'
import preact from '@preact/preset-vite'
import path from "path";

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    preact()
  ],
  resolve: {
    alias: {
      "@": path.resolve(__dirname, "./src")
    }
  },
  build: {
    cssCodeSplit: false,
    rollupOptions: {
      output: {
        format: "iife",
        name: "WP_WhatsApp_Chat_Plugin",
        assetFileNames: "wp-whatsapp-chat-plugin.css",
        chunkFileNames: "wp-whatsapp-chat-plugin.js",
        entryFileNames: "wp-whatsapp-chat-plugin.js",
        inlineDynamicImports: true
      }
    },
    outDir: path.resolve(__dirname, "../assets")
  }
})
