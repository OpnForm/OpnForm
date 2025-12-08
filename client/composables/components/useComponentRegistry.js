import { defineAsyncComponent } from 'vue'

const integrationRegistry = new Map([
  ['webhook', () => import('~/components/open/integrations/WebhookIntegration.vue')],
  ['email', () => import('~/components/open/integrations/EmailIntegration.vue')],
  ['slack', () => import('~/components/open/integrations/SlackIntegration.vue')],
  ['discord', () => import('~/components/open/integrations/DiscordIntegration.vue')],
  ['google_sheets', () => import('~/components/open/integrations/GoogleSheetsIntegration.vue')],
  ['telegram', () => import('~/components/open/integrations/TelegramIntegration.vue')],
  ['zapier', () => import('~/components/open/integrations/ZapierIntegration.vue')],
])

const clientOnlyHeavyFormComponentsRegistry = new Map([
  ['RichTextAreaInput', () => import('~/components/forms/heavy/RichTextAreaInput.client.vue')],
  ['PaymentInput', () => import('~/components/forms/heavy/PaymentInput.client.vue')],
  ['CodeInput', () => import('~/components/forms/heavy/CodeInput.client.vue')],
  ['EmbedMedia', () => import('~/components/forms/heavy/components/EmbedMedia.vue')],
])

const actionRegistry = new Map([
  ['WebhookIntegrationActions', () => import('~/components/open/integrations/components/WebhookIntegrationActions.vue')],
  ['SlackIntegrationActions', () => import('~/components/open/integrations/components/SlackIntegrationActions.vue')],
  ['DiscordIntegrationActions', () => import('~/components/open/integrations/components/DiscordIntegrationActions.vue')],
  ['GoogleSheetsIntegrationActions', () => import('~/components/open/integrations/components/GoogleSheetsIntegrationActions.vue')],
  ['EmailIntegrationActions', () => import('~/components/open/integrations/components/EmailIntegrationActions.vue')],
  ['N8nIntegrationActions', () => import('~/components/open/integrations/components/N8nIntegrationActions.vue')],
  ['ActivepiecesIntegrationActions', () => import('~/components/open/integrations/components/ActivepiecesIntegrationActions.vue')],
])

const providerRegistry = new Map([
  ['TelegramWidget', () => import('~/components/provider/widget/TelegramWidget.vue')],
])

const heavyFormComponentsRegistry = new Map([
  ['BarcodeInput', () => import('~/components/forms/heavy/BarcodeInput.vue')],
  ['DateInput', () => import('~/components/forms/heavy/DateInput.vue')],
  ['SignatureInput', () => import('~/components/forms/heavy/SignatureInput.vue')],
  ['FileInput', () => import('~/components/forms/heavy/FileInput.vue')],
  ['ImageInput', () => import('~/components/forms/heavy/ImageInput.vue')],
  ['LogicConfirmationModal', () => import('~/components/forms/heavy/LogicConfirmationModal.vue')],
  ['MatrixInput', () => import('~/components/forms/heavy/MatrixInput.vue')],
  ['PhoneInput', () => import('~/components/forms/heavy/PhoneInput.vue')],
  ['RatingInput', () => import('~/components/forms/heavy/RatingInput.vue')],
])

const focusedFormComponentsRegistry = new Map([
  ['FocusedSelectorInput', () => import('~/components/forms/core/FocusedSelectorInput.vue')],
  ['FocusedToggleInput', () => import('~/components/forms/core/FocusedToggleInput.vue')],
])

// Component loading cache to avoid duplicate imports
const componentCache = new Map()

/**
 * Composable for lazy loading components from registries
 */
export function useComponentRegistry() {
  
  /**
   * Load an integration component by ID
   * @param {string} integrationId - The integration identifier
   * @returns {Promise<Component>} The loaded component
   */
  const loadIntegrationComponent = async (integrationId) => {
    const cacheKey = `integration:${integrationId}`
    
    if (componentCache.has(cacheKey)) {
      return componentCache.get(cacheKey)
    }

    const loader = integrationRegistry.get(integrationId)
    if (!loader) {
      console.warn(`Integration component not found: ${integrationId}`)
      return null
    }

    try {
      const component = await loader()
      const resolvedComponent = component.default || component
      componentCache.set(cacheKey, resolvedComponent)
      return resolvedComponent
    } catch (error) {
      console.error(`Failed to load integration component: ${integrationId}`, error)
      return null
    }
  }

  /**
   * Load an action component by name
   * @param {string} actionName - The action component name
   * @returns {Promise<Component>} The loaded component
   */
  const loadActionComponent = async (actionName) => {
    const cacheKey = `action:${actionName}`
    
    if (componentCache.has(cacheKey)) {
      return componentCache.get(cacheKey)
    }

    const loader = actionRegistry.get(actionName)
    if (!loader) {
      console.warn(`Action component not found: ${actionName}`)
      return null
    }

    try {
      const component = await loader()
      const resolvedComponent = component.default || component
      componentCache.set(cacheKey, resolvedComponent)
      return resolvedComponent
    } catch (error) {
      console.error(`Failed to load action component: ${actionName}`, error)
      return null
    }
  }

  /**
   * Load a provider widget component by name
   * @param {string} widgetName - The widget component name
   * @returns {Promise<Component>} The loaded component
   */
  const loadProviderWidget = async (widgetName) => {
    const cacheKey = `provider:${widgetName}`
    
    if (componentCache.has(cacheKey)) {
      return componentCache.get(cacheKey)
    }

    const loader = providerRegistry.get(widgetName)
    if (!loader) {
      console.warn(`Provider widget not found: ${widgetName}`)
      return null
    }

    try {
      const component = await loader()
      const resolvedComponent = component.default || component
      componentCache.set(cacheKey, resolvedComponent)
      return resolvedComponent
    } catch (error) {
      console.error(`Failed to load provider widget: ${widgetName}`, error)
      return null
    }
  }

  /**
   * Create a reactive async component that handles loading states
   * @param {Function} loader - Function that returns a promise resolving to a component
   * @returns {Object} Reactive component with loading/error states
   */
  const createAsyncComponent = (loader) => {
    return defineAsyncComponent({
      loader,
      loadingComponent: {
        template: '<div class="flex items-center justify-center p-4"><USkeleton class="h-8 w-full" /></div>'
      },
      errorComponent: {
        template: '<div class="text-sm text-red-500 p-4">Failed to load component</div>'
      },
      delay: 200,
      timeout: 10000
    })
  }

  /**
   * Resolves a form component. If the component is registered as "heavy",
   * it returns an async component definition for lazy-loading.
   * Otherwise, it returns the component name as a string for global resolution.
   * @param {string} componentName - The name of the form component.
   * @returns {Object|string} An async component definition or a string name.
   */
  const getFormComponent = (componentName) => {
    if (!componentName) {
      return null
    }

    if (clientOnlyHeavyFormComponentsRegistry.has(componentName)) {
      const loader = clientOnlyHeavyFormComponentsRegistry.get(componentName)
      return {
        component: createAsyncComponent(loader),
        clientOnly: true
      }
    }

    if (heavyFormComponentsRegistry.has(componentName)) {
      const loader = heavyFormComponentsRegistry.get(componentName)
      return {
        component: createAsyncComponent(loader),
        clientOnly: false
      }
    }

    if (focusedFormComponentsRegistry.has(componentName)) {
      const loader = focusedFormComponentsRegistry.get(componentName)
      return {
        component: createAsyncComponent(loader),
        clientOnly: false
      }
    }

    return {
      component: componentName,
      clientOnly: false
    }
  }

  /**
   * Get a reactive integration component
   * @param {string} integrationId - The integration identifier
   * @returns {Object} Async component with loading states
   */
  const getIntegrationComponent = (integrationId) => {
    return createAsyncComponent(() => loadIntegrationComponent(integrationId))
  }

  /**
   * Get a reactive action component
   * @param {string} actionName - The action component name
   * @returns {Object} Async component with loading states
   */
  const getActionComponent = (actionName) => {
    return createAsyncComponent(() => loadActionComponent(actionName))
  }

  /**
   * Get a reactive provider widget component
   * @param {string} widgetName - The widget component name
   * @returns {Object} Async component with loading states
   */
  const getProviderWidget = (widgetName) => {
    return createAsyncComponent(() => loadProviderWidget(widgetName))
  }

  return {
    loadIntegrationComponent,
    loadActionComponent,
    loadProviderWidget,
    getFormComponent,
    getIntegrationComponent,
    getActionComponent,
    getProviderWidget,
    createAsyncComponent
  }
} 