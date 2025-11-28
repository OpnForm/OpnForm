// https://nuxt.com/docs/api/configuration/nuxt-config
import runtimeConfig from "./runtimeConfig"
import sitemap from "./sitemap"

export default defineNuxtConfig({
  loglevel: process.env.NUXT_LOG_LEVEL || 'info',
  devtools: {enabled: true},
  css: ['~/css/app.css'],

  // Disable certain plugins during testing
  modules: process.env.VITEST ? [] : [
      '@pinia/nuxt', 
      '@vueuse/nuxt', 
      '@vueuse/motion/nuxt', 
      '@nuxtjs/sitemap',
      '@nuxt/ui', 
      'nuxt-utm', 
      '@nuxtjs/i18n',
      '@nuxt/icon', 
      '@sentry/nuxt/module',
      '@zadigetvoltaire/nuxt-gtm',
  ],

  // Skip plugin initialization during tests
  plugins: process.env.VITEST ? [
      // Only include plugins safe for testing
  ] : [
      // Full plugin list for production/dev
  ],

  build: {
      transpile: ["vue-notion", "vue-signature-pad", "@zxing/library"],
  },

  i18n: {
      locales: [
        { code: 'en', name: 'English', iso: 'en-US', file: 'en.json' },
        { code: 'fr', name: 'French', iso: 'fr-FR', file: 'fr.json' },
        { code: 'hi', name: 'Hindi', iso: 'hi-IN', file: 'hi.json' },
        { code: 'es', name: 'Spanish', iso: 'es-ES', file: 'es.json' },
        { code: 'ar', name: 'Arabic', iso: 'ar-EG', file: 'ar.json' },
        { code: 'zh', name: 'Chinese', iso: 'zh-CN', file: 'zh.json' },
        { code: 'ja', name: 'Japanese', iso: 'ja-JP', file: 'ja.json' },
        { code: 'bn', name: 'Bengali', iso: 'bn-BD', file: 'bn.json' },
        { code: 'pt', name: 'Portuguese', iso: 'pt-BR', file: 'pt.json' },
        { code: 'ru', name: 'Russian', iso: 'ru-RU', file: 'ru.json' },
        { code: 'ur', name: 'Urdu', iso: 'ur-PK', file: 'ur.json' },
        { code: 'pa', name: 'Punjabi', iso: 'pa-IN', file: 'pa.json' },
        { code: 'de', name: 'German', iso: 'de-DE', file: 'de.json' },
        { code: 'jv', name: 'Javanese', iso: 'jv-ID', file: 'jv.json' },
        { code: 'ko', name: 'Korean', iso: 'ko-KR', file: 'ko.json' },
        { code: 'vi', name: 'Vietnamese', iso: 'vi-VN', file: 'vi.json' },
        { code: 'te', name: 'Telugu', iso: 'te-IN', file: 'te.json' },
        { code: 'mr', name: 'Marathi', iso: 'mr-IN', file: 'mr.json' },
        { code: 'ta', name: 'Tamil', iso: 'ta-IN', file: 'ta.json' },
        { code: 'tr', name: 'Turkish', iso: 'tr-TR', file: 'tr.json' },
        { code: 'sk', name: 'Slovak', iso: 'sk-SK', file: 'sk.json' },
        { code: 'cs', name: 'Czech', iso: 'cs-CZ', file: 'cs.json' },
        { code: 'eu', name: 'Basque', iso: 'eu-ES', file: 'eu.json' },
        { code: 'gl', name: 'Galician', iso: 'gl-ES', file: 'gl.json' },
        { code: 'ca', name: 'Valencian/Catalan', iso: 'ca-ES', file: 'ca.json' },
        { code: 'sv', name: 'Swedish', iso: 'sv-SE', file: 'sv.json' },
        { code: 'pl', name: 'Polish', iso: 'pl-PL', file: 'pl.json' },
        { code: 'nl', name: 'Dutch', iso: 'nl-NL', file: 'nl.json' },
        { code: 'sr', name: 'Serbian', iso: 'sr-RS', file: 'sr.json' },
        { code: 'uk', name: 'Ukrainian', iso: 'uk-UA', file: 'uk.json' },
      ],
      defaultLocale: 'en',
      lazy: true,
      langDir: 'lang/',
      strategy: 'no_prefix',
      detectBrowserLanguage: {
          cookieSecure: true
      }
  },

  experimental: {
      inlineRouteRules: true
  },

  sentry: {
      sourceMapsUploadOptions: {
          authToken: process.env.SENTRY_AUTH_TOKEN,
          org: "opnform",
          project: "opnform-vue",
      },
  },

  sourcemap: { client: 'hidden' },

  gtag: {
      id: process.env.NUXT_PUBLIC_GOOGLE_ANALYTICS_CODE,
  },

  ui: {
    theme: {
        colors: [
            'primary',
            'secondary',
            'success',
            'error',
            'warning',
            'info',
            'neutral',
            'form'
        ]
    }
  },

  components: [
      {
          path: '~/components/forms/core',
          pathPrefix: false,
          global: true,
      },
      {
          path: '~/components/forms/heavy',
          pathPrefix: false,
          global: false,
      },
      {
          path: '~/components/global',
          pathPrefix: false,
      },
      {
          path: '~/components/pages',
          pathPrefix: false,
      },
      '~/components',
  ],

  colorMode: {
      preference: 'light',
      fallback: 'light',
      classPrefix: '',
  },

  icon: {
      customCollections: [
          {
              prefix: 'opnform',
              dir: './public/icons'
          },
      ],
      clientBundle: {
          includeCustomCollections: true,
          scan: {
              globInclude: ['**/*.vue', '**/*.json'],
          },
      },
    },

  devServer: {
    host: process.env.NUXT_HOST || 'localhost',
    port: Number(process.env.NUXT_PORT) || 3000,
  },

  sitemap,
  runtimeConfig,
  compatibilityDate: '2024-10-30'
})
