import { agentDraftApi } from '../../utils/agentDraftApi'

export default defineEventHandler((event) => agentDraftApi(event, '/agent-drafts/editor/current'))
