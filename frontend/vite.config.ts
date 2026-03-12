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
        name: "WP_WhatsApp_Multi_Chat_Plugin",
        assetFileNames: "css/wp-whatsapp-multi-chat-plugin.css",
        chunkFileNames: "js/wp-whatsapp-multi-chat-plugin.js",
        entryFileNames: "js/wp-whatsapp-multi-chat-plugin.js",
        inlineDynamicImports: true
      }
    },
    outDir: path.resolve(__dirname, "../assets")
  }
})
