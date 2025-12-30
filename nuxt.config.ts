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
