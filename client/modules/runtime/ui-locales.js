import { resolveUiLocaleKey } from '~/lib/ui-locales'
import { en } from '@nuxt/ui/locale'
import { localeContextInjectionKey } from '#ui/composables/useLocale'

const localeModules = import.meta.glob('#ui/locale/*.js', { import: 'default' })
const loaders = Object.fromEntries(Object.entries(localeModules).map(([path, loader]) => [
  path.split('/').pop().replace('.js', ''), loader
]))

export default defineNuxtPlugin({
  name: 'ui-locales',
  enforce: 'post',
  setup(nuxtApp) {
    const localeCode = computed(() => unref(nuxtApp.$i18n?.locale) || 'en')
    const locales = useState('ui-locales', () => ({ en }))
    const available = Object.keys(loaders)
    const loadLocale = (locale) => {
      const code = resolveUiLocaleKey(locale, available)
      if (locales.value[code]) return Promise.resolve()
      return loaders[code]().then((loaded) => { locales.value[code] = loaded })
    }
    // Load before switching so UI controls and vue-i18n change language together.
    // State is serialized per request, avoiding a shared SSR locale cache.
    nuxtApp.hook('i18n:beforeLocaleSwitch', ({ newLocale }) => loadLocale(newLocale))
    nuxtApp.vueApp.provide(localeContextInjectionKey, computed(() => {
      return locales.value[resolveUiLocaleKey(localeCode.value, available)] || en
    }))
    return loadLocale(localeCode.value)
  },
})
