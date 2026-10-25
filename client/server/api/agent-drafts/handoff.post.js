import { agentDraftApi, setAgentDraftSession } from '../../utils/agentDraftApi'

export default defineEventHandler(async (event) => {
  const body = await readBody(event)
  const response = await agentDraftApi(event, '/agent-drafts/handoff/consume', {
    method: 'POST',
    body: { handoff_token: body?.handoff_token },
  })

  setAgentDraftSession(event, response.editor_session, response.draft.expires_at)

  return { draft: response.draft }
})
