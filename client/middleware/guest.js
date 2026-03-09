const UUID_REGEX = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i

export default defineNuxtRouteMiddleware((to) => {
  const { isAuthenticated } = useIsAuthenticated()
  if (isAuthenticated.value) {
    const gptChatId = typeof to.query.gpt_chat_id === 'string' ? to.query.gpt_chat_id : null
    if (to.path === '/forms/create/guest' && gptChatId && UUID_REGEX.test(gptChatId)) {
      return navigateTo({
        path: '/forms/create',
        query: {
          ...to.query,
          gpt_chat_id: gptChatId,
        },
      })
    }

    return navigateTo({ name: "home" })
  }
})
