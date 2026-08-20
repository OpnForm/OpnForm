import { describe, expect, it } from 'vitest'
import { getAgentDraftSessionRequest } from '../../lib/forms/agent-draft-session'

describe('agent draft editor session requests', () => {
  it('consumes a handoff capability from the URL fragment', () => {
    expect(getAgentDraftSessionRequest('#handoff=secret-capability')).toEqual({
      url: '/api/agent-drafts/handoff',
      options: {
        method: 'POST',
        body: { handoff_token: 'secret-capability' },
      },
      consumesHandoff: true,
    })
  })

  it('restores the existing cookie session after a refresh', () => {
    expect(getAgentDraftSessionRequest()).toEqual({
      url: '/api/agent-drafts/current',
      options: undefined,
      consumesHandoff: false,
    })
  })
})
