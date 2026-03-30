import { contentApi } from '~/api/content'

export default defineNuxtPlugin(async (nuxtApp) => {
  const planCatalogState = useState('planCatalog', () => ({ tiers: {} }))

  try {
    const plans = await contentApi.plans.list({ server: true })
    if (plans?.tiers) {
      planCatalogState.value = plans
    }
  } catch (error) {
    console.error('Failed to load plan catalog on server:', error)
  }

  nuxtApp.provide('refreshPlanCatalog', async () => {
    const plans = await contentApi.plans.list({
      query: { t: Date.now() }
    })
    if (plans?.tiers) {
      planCatalogState.value = plans
    }
    return planCatalogState.value
  })
})
