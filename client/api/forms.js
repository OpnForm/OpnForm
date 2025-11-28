import { apiService } from './base'

export const formsApi = {
  // Form views
  view: (slug, options) => apiService.get(`/forms/${slug}/view`, options),

  // Form operations
  list: (workspaceId, options) => apiService.get(`/open/workspaces/${workspaceId}/forms`, options),
  get: (slug, options) => apiService.get(`/open/forms/${slug}`, options),
  getById: (id, options) => apiService.get(`/open/forms/${id}`, options),
  publicGet: (slug, options) => apiService.get(`/forms/${slug}`, options),
  publicGetById: (id, options) => apiService.get(`/forms/${id}`, options),
  
  create: (data) => apiService.post('/open/forms', data),
  update: (id, data) => apiService.put(`/open/forms/${id}`, data),
  delete: (id) => apiService.delete(`/open/forms/${id}`),
  duplicate: (id) => apiService.post(`/open/forms/${id}/duplicate`),

  // Form utilities
  regenerateLink: (id, option) => apiService.put(`/open/forms/${id}/regenerate-link/${option}`),
  mobileEditorEmail: (id) => apiService.get(`/open/forms/${id}/mobile-editor-email`),
  updateWorkspace: (id, workspaceId, data) => apiService.post(`/open/forms/${id}/workspace/${workspaceId}`, data),

  // Form submissions
  submissions: {
    list: (formId, options) => apiService.get(`/open/forms/${formId}/submissions`, options),
    get: (slug, submissionId, options) => apiService.get(`/forms/${slug}/submissions/${submissionId}`, options),
    update: (formId, submissionId, data) => apiService.put(`/open/forms/${formId}/submissions/${submissionId}`, data),
    delete: (formId, submissionId) => apiService.delete(`/open/forms/${formId}/submissions/${submissionId}`),
    deleteMulti: (formId, submissionIds) => apiService.post(`/open/forms/${formId}/submissions/multi`, { submissionIds }),
    export: (formId, data) => apiService.post(`/open/forms/${formId}/submissions/export`, data),
    exportStatus: (formId, jobId) => apiService.get(`/open/forms/${formId}/submissions/export/status/${jobId}`),
    answer: (slug, data, options) => apiService.post(`/forms/${slug}/answer`, data, options)
  },

  // Form stats
  stats: (workspaceId, formId, options) => apiService.get(`/open/workspaces/${workspaceId}/form-stats/${formId}`, options),
  statsDetails: (workspaceId, formId, options) => apiService.get(`/open/workspaces/${workspaceId}/form-stats-details/${formId}`, options),

  // File operations
  assets: {
    upload: (data, options) => apiService.post('/open/forms/assets/upload', data, options),
    view: (formId, filename, options) => apiService.get(`/open/forms/${formId}/uploaded-file/${filename}`, options)
  },

  // AI form generation
  ai: {
    generate: (data) => apiService.post('/forms/ai/generate', data),
    generateFields: (data) => apiService.post('/forms/ai/generate-fields', data),
    get: (generationId, options) => apiService.get(`/forms/ai/${generationId}`, options)
  },

  // Stripe/Payment
  stripe: {
    getAccount: (slug, options) => apiService.get(`/forms/${slug}/stripe-connect/get-account`, options),
    createPaymentIntent: (slug, data) => apiService.post(`/forms/${slug}/stripe-connect/payment-intent`, data)
  },

  // Integrations
  integrations: {
    list: (formId, options) => apiService.get(`/open/forms/${formId}/integrations`, options),
    create: (formId, data) => apiService.post(`/open/forms/${formId}/integrations`, data),
    update: (formId, integrationId, data) => apiService.put(`/open/forms/${formId}/integrations/${integrationId}`, data),
    delete: (formId, integrationId) => apiService.delete(`/open/forms/${formId}/integrations/${integrationId}`),
    events: (formId, integrationId, options) => apiService.get(`/open/forms/${formId}/integrations/${integrationId}/events`, options)
  },

  // Zapier webhooks
  zapier: {
    store: (data) => apiService.post('/open/forms/webhooks/zapier', data),
    delete: (id) => apiService.delete(`/open/forms/webhooks/zapier/${id}`)
  }
}