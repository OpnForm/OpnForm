export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook('request', (event) => {
    if (import.meta.dev) return
    const config = useRuntimeConfig(event).public
    if (config.codexAutoLogin || config.codexAutoLoginEmail || config.codexAutoLoginPassword) {
      throw createError({ statusCode: 503, statusMessage: 'Development auto-login must be disabled in production.' })
    }
  })
})
