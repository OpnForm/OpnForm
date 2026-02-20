import { useMutation, useQuery, useQueryClient } from '@tanstack/vue-query'
import { formsApi } from '~/api/forms'

export function usePdfTemplates() {
  const queryClient = useQueryClient()

  const domainKey = (formId) => ['forms', toValue(formId), 'pdf-templates']
  const listKey = (formId) => [...domainKey(formId), 'list']
  const detailKey = (formId, templateId) => [...domainKey(formId), 'detail', toValue(templateId)]

  const list = (formId, queryOptions = {}) => {
    return useQuery({
      queryKey: computed(() => listKey(formId)),
      queryFn: () => formsApi.pdfTemplates.list(toValue(formId)),
      enabled: computed(() => !!toValue(formId)),
      staleTime: 5 * 60 * 1000,
      ...queryOptions,
    })
  }

  const detail = (formId, templateId, queryOptions = {}) => {
    return useQuery({
      queryKey: computed(() => detailKey(formId, templateId)),
      queryFn: () => formsApi.pdfTemplates.get(toValue(formId), toValue(templateId)),
      enabled: computed(() => !!toValue(formId) && !!toValue(templateId)),
      staleTime: 5 * 60 * 1000,
      ...queryOptions,
    })
  }

  const invalidate = (formId) => {
    return queryClient.invalidateQueries({ queryKey: domainKey(formId) })
  }

  const upload = (formId, mutationOptions = {}) => {
    const { onSuccess, ...restMutationOptions } = mutationOptions

    return useMutation({
      mutationFn: (data) => formsApi.pdfTemplates.upload(toValue(formId), data),
      onSuccess: (...args) => {
        invalidate(formId)
        onSuccess?.(...args)
      },
      ...restMutationOptions,
    })
  }

  const createFromScratch = (formId, mutationOptions = {}) => {
    const { onSuccess, ...restMutationOptions } = mutationOptions

    return useMutation({
      mutationFn: (data) => formsApi.pdfTemplates.createFromScratch(toValue(formId), data),
      onSuccess: (...args) => {
        invalidate(formId)
        onSuccess?.(...args)
      },
      ...restMutationOptions,
    })
  }

  const update = (formId, templateId, mutationOptions = {}) => {
    const { onSuccess, ...restMutationOptions } = mutationOptions

    return useMutation({
      mutationFn: (data) => formsApi.pdfTemplates.update(toValue(formId), toValue(templateId), data),
      onSuccess: (...args) => {
        invalidate(formId)
        onSuccess?.(...args)
      },
      ...restMutationOptions,
    })
  }

  const remove = (formId, mutationOptions = {}) => {
    const { onSuccess, ...restMutationOptions } = mutationOptions

    return useMutation({
      mutationFn: (templateId) => formsApi.pdfTemplates.delete(toValue(formId), templateId),
      onSuccess: (...args) => {
        invalidate(formId)
        onSuccess?.(...args)
      },
      ...restMutationOptions,
    })
  }

  return {
    list,
    detail,
    upload,
    createFromScratch,
    update,
    remove,
    invalidate,
  }
}
