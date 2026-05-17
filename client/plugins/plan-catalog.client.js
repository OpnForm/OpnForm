import { contentApi } from '~/api/content'

export default defineNuxtPlugin((nuxtApp) => {
  const planCatalogState = useState('planCatalog', () => ({ tiers: {} }))

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
