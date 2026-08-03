import type {
  PublicDesignerProfile,
  PublicDesignerProfileResponse,
} from '~/types/public-designer-profile'

interface PublicProfileFailure {
  status?: number
  statusCode?: number
  response?: { status?: number }
}

export async function usePublicDesignerProfile(username: string) {
  const config = useRuntimeConfig()
  const encodedUsername = encodeURIComponent(username)

  const request = await useAsyncData<PublicDesignerProfileResponse>(
    `public-designer-profile:${username}`,
    () => $fetch<PublicDesignerProfileResponse>(`/designers/${encodedUsername}`, {
      baseURL: config.public.apiBaseUrl,
      headers: { Accept: 'application/json' },
    }),
    {
      server: true,
      lazy: true,
      dedupe: 'defer',
    },
  )

  const profile = computed<PublicDesignerProfile | null>(() =>
    request.data.value?.data.profile ?? null,
  )
  const errorStatus = computed<number | null>(() => {
    const failure = request.error.value as PublicProfileFailure | null
    return failure?.statusCode ?? failure?.status ?? failure?.response?.status ?? null
  })

  return {
    profile,
    pending: computed(() => request.status.value === 'pending'),
    error: request.error,
    errorStatus,
    retry: request.refresh,
  }
}
