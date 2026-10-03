import { agentDraftApi } from '../../utils/agentDraftApi'

export default defineEventHandler(async (event) => agentDraftApi(event, '/agent-drafts/editor/claim', {
  method: 'POST',
  body: await readBody(event),
  includeAuth: true,
}))
