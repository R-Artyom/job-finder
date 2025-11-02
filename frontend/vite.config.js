// Автодополнение и подсветка типов
import { defineConfig } from 'vite'
// Плагин для сборки Vue-компонентов
import vue from '@vitejs/plugin-vue'
// СSS-фреймворк
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  // Регистрация плагинов в Vite
  plugins: [
    vue(),
    tailwindcss(),
  ],
  // Настройка дев-сервера при запуске "npm run dev", перехват и проксирование запросов /api на http://172.17.0.1:8080/api/
  server: {
    proxy: {
      '/api': 'http://172.17.0.1:8080', // Адрес Laravel
    },
  },
})