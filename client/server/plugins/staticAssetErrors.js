export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook('render:response', (response) => {
    // Route headers also apply to missing assets. Never retain error documents.
    if (response.statusCode >= 400) {
      response.headers['cache-control'] = 'no-store'
      delete response.headers['Cache-Control']
    }
  })
})
