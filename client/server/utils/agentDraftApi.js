const SESSION_COOKIE = 'opnform_agent_draft_session'

function apiHeaders(event, includeAuth = false) {
  const config = useRuntimeConfig(event)
  const headers = {
    accept: 'application/json',
    'x-api-secret': config.apiSecret,
  }
  const session = getCookie(event, SESSION_COOKIE)
  if (session) {
    headers['x-agent-draft-session'] = session
  }
  if (includeAuth) {
    const token = getCookie(event, 'opnform_token')
    if (token) {
      headers.authorization = `Bearer ${token}`
    }
  }
  return headers
}

export async function agentDraftApi(event, path, options = {}) {
  const config = useRuntimeConfig(event)
  const apiBase = config.privateApiBase || config.public.apiBase
  const { includeAuth = false, headers: optionHeaders, ...fetchOptions } = options

  try {
    return await $fetch(path, {
      baseURL: apiBase,
      ...fetchOptions,
      headers: {
        ...apiHeaders(event, includeAuth),
        ...optionHeaders,
      },
    })
  } catch (error) {
    throw createError({
      statusCode: error?.statusCode || error?.response?.status || 500,
      statusMessage: error?.data?.message || error?.message || 'Agent draft request failed',
      data: error?.data,
    })
  }
}

export function setAgentDraftSession(event, session, expiresAt) {
  const requestUrl = getRequestURL(event)
  const maxAge = Math.max(1, Math.floor((new Date(expiresAt).getTime() - Date.now()) / 1000))
  setCookie(event, SESSION_COOKIE, session, {
    httpOnly: true,
    secure: requestUrl.protocol === 'https:',
    sameSite: 'lax',
    path: '/',
    maxAge,
  })
}
