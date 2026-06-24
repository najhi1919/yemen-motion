import { defineNuxtConfig } from 'nuxt/config'

export default defineNuxtConfig({
  // Global page headers
  app: {
    head: {
      title: 'Yemen Motion',
      htmlAttrs: { lang: 'ar' },
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { hid: 'description', name: 'description', content: 'منصة يمن موشن' }
      ]
    }
  },

  // Tailwind CSS integration
  modules: ['@nuxtjs/tailwindcss', '@pinia/nuxt'],

  css: ['~/assets/auth.scss'],

  postcss: {
    plugins: {
      tailwindcss: {},
      autoprefixer: {}
    }
  },

  // Pinia auto import
  imports: {
    dirs: ['stores']
  },

  // Public runtime config – API base URL
  runtimeConfig: {
    public: {
      apiBaseUrl: process.env.NUXT_PUBLIC_API_BASE_URL || 'http://127.0.0.1:8000/api'
    }
  }
})
