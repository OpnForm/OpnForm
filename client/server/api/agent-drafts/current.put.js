import { agentDraftApi } from '../../utils/agentDraftApi'

export default defineEventHandler(async (event) => agentDraftApi(event, '/agent-drafts/editor/current', {
  method: 'PUT',
  body: await readBody(event),
}))
