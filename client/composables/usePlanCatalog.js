import { useQuery } from '@tanstack/vue-query'

const PLAN_CATALOG_QUERY_KEY = ['content', 'plans']

export function usePlanCatalog() {
  const catalog = useState('planCatalog', () => ({ tiers: {} }))
  const tiers = computed(() => catalog.value?.tiers ?? {})
  const hasCatalog = computed(() => Object.keys(tiers.value).length > 0)

  const query = useQuery({
    queryKey: PLAN_CATALOG_QUERY_KEY,
    queryFn: () => $fetch('/api/plan-catalog'),
    staleTime: 10 * 60 * 1000,
    initialData: () => hasCatalog.value ? catalog.value : undefined,
  })

  function syncCatalog(plans) {
    if (plans?.tiers) {
      catalog.value = plans
    }

    return catalog.value
  }

  watch(query.data, (plans) => {
    syncCatalog(plans)
  }, { immediate: true })

  function suspense() {
    if (hasCatalog.value) {
      return Promise.resolve(catalog.value)
    }

    return query.suspense().then(() => syncCatalog(query.data.value))
  }

  function refresh() {
    return query.refetch().then(({ data }) => syncCatalog(data))
  }

  return {
    catalog,
    tiers,
    query,
    suspense,
    refresh,
  }
}
