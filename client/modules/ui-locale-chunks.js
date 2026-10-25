import { addPlugin, createResolver, defineNuxtModule, hasNuxtModule } from '@nuxt/kit'

// Nuxt UI 4 eagerly imports every configured UI locale. Load the active locale
// separately so public forms retain their existing payload budget.
export default defineNuxtModule({
  meta: { name: 'opnform-ui-locales' },
  setup(_options, nuxt) {
    if (!hasNuxtModule('@nuxt/ui')) return
    const resolver = createResolver(import.meta.url)
    nuxt.hook('app:resolve', (app) => {
      app.plugins = app.plugins.filter(plugin => !plugin.src.endsWith('/ui-i18n.mjs'))
    })
    addPlugin(resolver.resolve('./runtime/ui-locales'))
  },
})
