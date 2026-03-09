import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { toValue } from 'vue'
import { formsApi } from '~/api/forms'

export function useChatGptDrafts() {
  const queryClient = useQueryClient()

  const detail = (gptChatId, options = {}) => {
    const chatId = toValue(gptChatId)

    return useQuery({
      queryKey: ['chatgpt', 'drafts', chatId],
      queryFn: () => formsApi.chatgpt.getDraft(chatId),
      enabled: !!chatId,
      ...options,
    })
  }

  const fetchDraft = (gptChatId) => {
    const chatId = toValue(gptChatId)
    return queryClient.fetchQuery({
      queryKey: ['chatgpt', 'drafts', chatId],
      queryFn: () => formsApi.chatgpt.getDraft(chatId),
    })
  }

  const create = (options = {}) => {
    return useMutation({
      mutationFn: (payload) => formsApi.chatgpt.createDraft(payload),
      onSuccess: (response) => {
        const draft = response?.draft
        if (draft?.gpt_chat_id) {
          queryClient.setQueryData(['chatgpt', 'drafts', draft.gpt_chat_id], response)
        }
      },
      ...options,
    })
  }

  const update = (gptChatId, options = {}) => {
    return useMutation({
      mutationFn: (payload) => formsApi.chatgpt.updateDraft(toValue(gptChatId), payload),
      onSuccess: (response) => {
        const draft = response?.draft
        if (draft?.gpt_chat_id) {
          queryClient.setQueryData(['chatgpt', 'drafts', draft.gpt_chat_id], response)
        }
      },
      ...options,
    })
  }

  const handoff = (gptChatId, options = {}) => {
    return useMutation({
      mutationFn: () => formsApi.chatgpt.handoffDraft(toValue(gptChatId)),
      onSuccess: (response) => {
        const draft = response?.draft
        if (draft?.gpt_chat_id) {
          queryClient.setQueryData(['chatgpt', 'drafts', draft.gpt_chat_id], response)
        }
      },
      ...options,
    })
  }

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['chatgpt', 'drafts'] })
  }

  return {
    detail,
    fetchDraft,
    create,
    update,
    handoff,
    invalidate,
  }
}
