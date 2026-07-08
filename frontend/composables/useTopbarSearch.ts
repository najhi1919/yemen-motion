type Locale = 'ar' | 'en'

type TopbarSearchConfig = {
  scope: string
  placeholder: Record<Locale, string>
  tooltip: Record<Locale, string>
}

function defaultTopbarSearchConfig(): TopbarSearchConfig {
  return {
    scope: 'platform',
    placeholder: {
      ar: 'ابحث في الطلبات، المستخدمين، البلاغات، التذاكر...',
      en: 'Search orders, users, reports, tickets...'
    },
    tooltip: {
      ar: 'البحث في المنصة',
      en: 'Search the platform'
    }
  }
}

export function useTopbarSearch() {
  const query = useState<string>('ym-topbar-search-query', () => '')
  const config = useState<TopbarSearchConfig>('ym-topbar-search-config', defaultTopbarSearchConfig)

  function setTopbarSearchConfig(nextConfig: TopbarSearchConfig): void {
    config.value = nextConfig
  }

  function resetTopbarSearchConfig(): void {
    config.value = defaultTopbarSearchConfig()
  }

  function clearTopbarSearch(): void {
    query.value = ''
  }

  return {
    query,
    config,
    setTopbarSearchConfig,
    resetTopbarSearchConfig,
    clearTopbarSearch
  }
}
