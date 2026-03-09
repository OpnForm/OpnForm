export default defineNuxtRouteMiddleware((to) => {
  const selfHosted = useFeatureFlag('self_hosted', true)
  const chatGptAppEnabled = useFeatureFlag('chatgpt_app.enabled', false)

  if (selfHosted || !chatGptAppEnabled) {
    throw createError({ statusCode: 404, statusMessage: 'Page Not Found' })
  }

  const gptChatId = Array.isArray(to.params.gpt_chat_id)
    ? to.params.gpt_chat_id[0]
    : to.params.gpt_chat_id

  if (typeof gptChatId !== 'string' || !/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(gptChatId)) {
    throw createError({ statusCode: 404, statusMessage: 'Page Not Found' })
  }
})
