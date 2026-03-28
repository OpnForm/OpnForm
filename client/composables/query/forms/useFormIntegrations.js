import { useQueryClient, useQuery, useMutation } from '@tanstack/vue-query'
import { formsApi } from '~/api/forms'
import integrationsList from '~/data/forms/integrations.json'
import { unref } from 'vue'
import { useAuth } from '~/composables/query/useAuth.js'
import { useFeatureFlag } from '~/composables/useFeatureFlag.js'

export function useFormIntegrations() {
  const queryClient = useQueryClient()
  const { user } = useAuth()
  const { data: userData } = user()

  // Static integrations data
  const integrations = ref(new Map())

  // Initialize integrations from JSON
  const initIntegrations = () => {
    if (integrations.value.size === 0) {
      integrations.value = new Map(Object.entries(integrationsList))
    }
  }

  // Computed property for available integrations based on user subscription and feature flags
  const availableIntegrations = computed(() => {
    if (!userData.value) return integrations.value

    const enrichedIntegrations = new Map()
    for (const [key, integration] of integrations.value.entries()) {
      if (useFeatureFlag(`integrations.${key}`, true)) {
        enrichedIntegrations.set(key, {
          ...integration,
          id: key,
          requires_subscription: !userData.value.is_subscribed && integration.is_pro,
        })
      }
    }
    return enrichedIntegrations
  })

  // Computed property for integrations grouped by section
  const integrationsBySection = computed(() => {
    const groupedObject = {}
    for (const [key, integration] of availableIntegrations.value.entries()) {
      const sectionName = integration.section_name
      if (!groupedObject[sectionName]) {
        groupedObject[sectionName] = {}
      }
      groupedObject[sectionName][key] = integration
    }
    return groupedObject
  })

  // TanStack Query functions
  const list = (formId, options = {}) => {
    return useQuery({
      queryKey: ['forms', unref(formId), 'integrations'],
      queryFn: () => formsApi.integrations.list(unref(formId), options),
      enabled: !!unref(formId),
      onSuccess: (data) => {
        const formIdValue = unref(formId)
        data?.forEach(integration => {
          queryClient.setQueryData(['forms', formIdValue, 'integrations', integration.id], integration)
        })
      },
      ...options
    })
  }

  const integrationEvents = (formId, integrationId, options = {}) => {
    return useQuery({
      queryKey: ['forms', unref(formId), 'integrations', unref(integrationId), 'events'],
      queryFn: () => formsApi.integrations.events(unref(formId), unref(integrationId), options),
      enabled: !!(unref(formId) && unref(integrationId)),
      ...options
    })
  }

  const createIntegration = (formId, options = {}) => {
    return useMutation({
      mutationFn: (data) => formsApi.integrations.create(toValue(formId), data),
      onSuccess: (response) => {
      const newIntegration = response.form_integration
      const currentFormId = toValue(formId)
      // Add to integrations list
      queryClient.setQueryData(['forms', currentFormId, 'integrations'], (old) => {
        if (!old) return [newIntegration]
        if (!Array.isArray(old)) return old
        return [...old, newIntegration]
      })
      // Cache the individual integration
      queryClient.setQueryData(['forms', currentFormId, 'integrations', newIntegration.id], newIntegration)
      },
      ...options
    })
  }

  const updateIntegration = (formId, integrationId, options = {}) => {
    return useMutation({
      mutationFn: (data) => formsApi.integrations.update(toValue(formId), toValue(integrationId), data),
      onSuccess: (response) => {
      const updatedIntegration = response.form_integration
      const currentFormId = toValue(formId)
      const currentIntegrationId = toValue(integrationId)
      // Update individual integration cache
      queryClient.setQueryData(['forms', currentFormId, 'integrations', currentIntegrationId], updatedIntegration)
      
      // Update in integrations list
      queryClient.setQueryData(['forms', currentFormId, 'integrations'], (old) => {
        if (!Array.isArray(old)) return old
        return old.map(integration =>
          integration.id === currentIntegrationId ? { ...integration, ...updatedIntegration } : integration
        )
      })
      },
      ...options
    })
  }

  const deleteIntegration = (options = {}) => {
    return useMutation({
      mutationFn: ({ formId, integrationId }) => formsApi.integrations.delete(formId, integrationId),
      onSuccess: (_, { formId, integrationId }) => {
      // Remove individual integration cache
      queryClient.removeQueries({ queryKey: ['forms', formId, 'integrations', integrationId] })
      
      // Remove from integrations list
      queryClient.setQueryData(['forms', formId, 'integrations'], (old) => {
        if (!Array.isArray(old)) return old
        return old.filter(integration => integration.id !== integrationId)
      })
      },
      ...options
    })
  }

  const invalidateIntegrations = (formId) => {
    const formIdValue = unref(formId)
    queryClient.invalidateQueries({ queryKey: ['forms', formIdValue, 'integrations'] })
  }

  // Invalidate all integration-related queries for a form
  const invalidateAllIntegrations = (formId) => {
    const formIdValue = unref(formId)
    queryClient.invalidateQueries({ 
      queryKey: ['forms', formIdValue, 'integrations']
    })
  }

  // Get a specific integration from cache
  const getIntegrationById = (formId, integrationId) => {
    const formIdValue = unref(formId)
    const integrationIdValue = unref(integrationId)
    return queryClient.getQueryData(['forms', formIdValue, 'integrations', integrationIdValue])
  }

  // Utility function to get all integrations by form ID from cache
  const getAllByFormId = (formId) => {
    const formIdValue = unref(formId)
    const queryData = queryClient.getQueryData(['forms', formIdValue, 'integrations'])
    return queryData || []
  }

  // Initialize integrations on first use
  initIntegrations()

  return {
    // Static data
    integrations: readonly(integrations),
    availableIntegrations,
    integrationsBySection,
    
    // TanStack Query functions
    list,
    integrationEvents,
    createIntegration,
    updateIntegration,
    deleteIntegration,
    invalidateIntegrations,
    invalidateAllIntegrations,
    
    // Utility functions
    getAllByFormId,
    getIntegrationById,
    initIntegrations,
  }
} 