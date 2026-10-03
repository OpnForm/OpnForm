export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook('render:response', (response, { event }) => {
    const path = (event.node?.req?.url || '').split('?')[0]
    if (/^\/(login|register|password|oauth|settings)(\/|$)/.test(path)) {
      response.headers['Cache-Control'] = 'private, no-store'
      response.headers.Pragma = 'no-cache'
    }
  })
})
