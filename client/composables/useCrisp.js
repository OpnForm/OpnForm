export function useCrisp () {
  const crisp = import.meta.client ? window.Crisp : null

  function onCrispInit () {
    if (!crisp)
      return
    crisp.chat.onChatOpened(() => {
      useAppStore().crisp.chatOpened = true
    })
    crisp.chat.onChatClosed(() => {
      useAppStore().crisp.chatOpened = false
    })
  }

  function openChat () {
    if (!crisp)
      return
    showChat()
    crisp.chat.open()
  }

  function showChat () {
    if (!crisp)
      return
    crisp.chat.show()
    useAppStore().crisp = {
      ...useAppStore().crisp,
      hidden: false
    }
  }

  function hideChat () {
    if (!crisp)
      return
    crisp.chat.hide()
    useAppStore().crisp = {
      ...useAppStore().crisp,
      hidden: true
    }
  }

  function closeChat () {
    if (!crisp)
      return
    crisp.chat.close()
  }

  function openAndShowChat (message = null) {
    if (!crisp)
      return
    openChat()
    if (message)
      sendTextMessage(message)
  }

  function openHelpdesk () {
    if (!crisp)
      return
    openChat()
    crisp.chat.setHelpdeskView()
  }

  function openHelpdeskArticle (articleSlug, locale = 'en') {
    if (!crisp)
      return
    crisp.chat.openHelpdeskArticle(locale, articleSlug)
  }

  function sendTextMessage (message) {
    if (!crisp)
      return
    crisp.message.send('text', message)
  }

  function setUser (user) {
    if (!crisp)
      return
    crisp.user.setEmail(user.email)
    crisp.user.setNickname(user.name)
    crisp.session.setData({
      'user_id': user.id,
      'pro-subscription': user?.is_subscribed ?? false,
      'stripe-id': user?.stripe_id ?? '',
      'subscription': user?.has_enterprise_subscription ? 'enterprise' : 'pro'
    })

    if (user?.is_subscribed ?? false) {
      setSegments([
        'subscribed',
        user?.has_enterprise_subscription ? 'enterprise' : 'pro'
      ])
    }
  }

  function pushEvent (event, data = {}) {
    if (!crisp)
      return
    crisp.session.pushEvent(event, data)
  }

  function setSegments (segments, overwrite = false) {
    if (!crisp)
      return
    crisp.session.setSegments(segments, overwrite)
  }

  // Send message as operator
  function showMessage (message, delay = 500) {
    if (!crisp)
      return
    setTimeout(() => {
      crisp.message.show('text', message)
    }, delay)
  }

  function pauseChatBot () {
    if (!crisp)
      return
    crisp.session.setData({ 'enum': 'pause_chatbot' })
  }

  function enableChatbot () {
    if (!crisp)
      return
    crisp.session.setData({ 'enum': 'start_chatbot' })
  }


  return {
    crisp,
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
