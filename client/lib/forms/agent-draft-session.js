export function getAgentDraftSessionRequest(hash = '') {
  const handoffToken = new URLSearchParams(hash.replace(/^#/, '')).get('handoff')

  if (!handoffToken) {
    return {
      url: '/api/agent-drafts/current',
      options: undefined,
      consumesHandoff: false,
    }
  }

  return {
    url: '/api/agent-drafts/handoff',
    options: {
      method: 'POST',
      body: { handoff_token: handoffToken },
    },
    consumesHandoff: true,
  }
}
