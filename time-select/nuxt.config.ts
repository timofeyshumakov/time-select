// Nuxt config file
import { defineNuxtConfig } from 'nuxt/config'
import ru from 'vuetify/lib/locale/ru.mjs'

export default defineNuxtConfig({
  modules: [
    'vuetify-nuxt-module'
  ],
  vuetify: {
    vuetifyOptions: {
      locale: {
        locale: 'ru',
        messages: { ru },
      },
      theme: {
        defaultTheme: 'light'
      }
    }
  },
  app: {
    baseURL: '/time-select/', // Важно: указывает базовый URL приложения
    buildAssetsDir: '/_nuxt/', // Путь отностительно baseURL
    cdnURL: 'https://renovoapp.webtm.ru/time-select',
    head: {
      script: [
        // Вариант 1: Простая библиотека
        {
          src: '//api.bitrix24.com/api/v1/',
          tagPosition: 'head',
          defer: true
        },
      ],
    }
  }
})
