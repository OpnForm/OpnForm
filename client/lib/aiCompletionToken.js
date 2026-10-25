const PREFIX = 'ai-completion:'

export function rememberAiCompletion(response) {
  if (response?.ai_form_completion_token && typeof window !== 'undefined') {
    window.sessionStorage.setItem(PREFIX + response.ai_form_completion_id, response.ai_form_completion_token)
  }
  return response
}

export function aiCompletionOptions(id, options = {}) {
  const token = typeof window !== 'undefined' ? window.sessionStorage.getItem(PREFIX + id) : null
  return { ...options, headers: { ...options.headers, ...(token ? { 'X-AI-Completion-Token': token } : {}) } }
}
