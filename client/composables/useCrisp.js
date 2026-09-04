export function useCrisp () {
  const ensureCrisp = () => {
    if (import.meta.server) return Promise.resolve(null)
    if (window.Crisp) return Promise.resolve(window.Crisp)
    return useNuxtApp().$ensureCrisp?.() || Promise.resolve(null)
  }

  const withCrisp = (callback) => ensureCrisp().then((crisp) => {
    if (crisp) return callback(crisp)
  })

  function onCrispInit () {
    return withCrisp((crisp) => {
      crisp.chat.onChatOpened(() => {
      useAppStore().crisp.chatOpened = true
      })
      crisp.chat.onChatClosed(() => {
      useAppStore().crisp.chatOpened = false
      })
    })
  }

  function openChat () {
    return withCrisp((crisp) => {
      crisp.chat.show()
      crisp.chat.open()
      useAppStore().crisp = { ...useAppStore().crisp, hidden: false }
    })
  }

  function showChat () {
    return withCrisp((crisp) => {
      crisp.chat.show()
      useAppStore().crisp = { ...useAppStore().crisp, hidden: false }
    })
  }

  function hideChat () {
    const crisp = import.meta.client ? window.Crisp : null
    if (!crisp) return Promise.resolve()
    crisp.chat.hide()
    useAppStore().crisp = { ...useAppStore().crisp, hidden: true }
  }

  function closeChat () {
    return withCrisp((crisp) => crisp.chat.close())
  }

  function openAndShowChat (message = null) {
    return openChat().then(() => {
      if (message) return sendTextMessage(message)
    })
  }

  function openHelpdesk () {
    return openChat().then(() => withCrisp((crisp) => crisp.chat.setHelpdeskView()))
  }

  function openHelpdeskArticle (articleSlug, locale = 'en') {
    return withCrisp((crisp) => crisp.chat.openHelpdeskArticle(locale, articleSlug))
  }

  function sendTextMessage (message) {
    return withCrisp((crisp) => crisp.message.send('text', message))
  }

  function setUser (user) {
    return withCrisp((crisp) => {
      crisp.user.setEmail(user.email)
      crisp.user.setNickname(user.name)
      crisp.session.setData({
      'user_id': user.id,
      'pro-subscription': user?.is_subscribed ?? false,
      'stripe-id': user?.stripe_id ?? '',
      'subscription': user?.plan_tier ?? 'free'
      })

      if (user?.is_subscribed ?? false) {
        crisp.session.setSegments(['subscribed', user?.plan_tier ?? 'free'])
      }
    })
  }

  function pushEvent (event, data = {}) {
    return withCrisp((crisp) => crisp.session.pushEvent(event, data))
  }

  function setSegments (segments, overwrite = false) {
    return withCrisp((crisp) => crisp.session.setSegments(segments, overwrite))
  }

  // Send message as operator
  function showMessage (message, delay = 500) {
    return withCrisp((crisp) => {
      setTimeout(() => crisp.message.show('text', message), delay)
    })
  }

  function pauseChatBot () {
    return withCrisp((crisp) => crisp.session.setData({ 'enum': 'pause_chatbot' }))
  }

  function enableChatbot () {
    return withCrisp((crisp) => crisp.session.setData({ 'enum': 'start_chatbot' }))
  }


  return {
    crisp: import.meta.client ? window.Crisp : null,
    onCrispInit,
    openChat,
    showChat,
    hideChat,
    closeChat,
    openAndShowChat,
    openHelpdesk,
    openHelpdeskArticle,
    sendTextMessage,
    pushEvent,
    setSegments,
    setUser,
    pauseChatBot,
    enableChatbot,
    showMessage
  }
}
