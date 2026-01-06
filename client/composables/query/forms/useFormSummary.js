import { useQuery } from '@tanstack/vue-query'
import { formsApi } from '~/api/forms'

export function useFormSummary() {
  const summary = (workspaceId, formId, options = {}) => {
    const dateFrom = options.dateFrom || ref(null)
    const dateTo = options.dateTo || ref(null)
    const status = options.status || ref('completed')

    return useQuery({
      queryKey: computed(() => [
        'forms',
        toValue(formId),
        'summary',
        toValue(dateFrom),
        toValue(dateTo),
        toValue(status)
      ]),
      queryFn: () => formsApi.summary(toValue(workspaceId), toValue(formId), {
        params: {
          date_from: toValue(dateFrom),
          date_to: toValue(dateTo),
          status: toValue(status)
        }
      }),
      staleTime: 5 * 60 * 1000, // 5 minutes
      enabled: computed(() => !!toValue(formId) && !!toValue(workspaceId)),
      ...options.queryOptions
    })
  }

  const fieldValues = (workspaceId, formId, fieldId, offset, filters = {}) => {
    return formsApi.summaryFieldValues(
      toValue(workspaceId),
      toValue(formId),
      toValue(fieldId),
      {
        params: {
          offset: toValue(offset),
          ...filters
        }
      }
    )
  }

  return {
    summary,
    fieldValues
  }
}

