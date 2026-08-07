import type {
  DesignerProfileOrganization,
  DesignerProfileOrganizationState,
  DesignerProfileOrganizationPayload,
  DesignerProfileOrganizationGetResponse,
  DesignerProfileOrganizationMutationResponse,
  DesignerProfileOrganizationLogoDeleteResponse,
} from '~/types/designer-profile-organization'

export const useDesignerProfileOrganization = () => {
  const config = useRuntimeConfig()
  const { apiFetch, tokenCookie } = useApiClient()
  const baseUrl = (config.public.apiBaseUrl as string) || 'http://127.0.0.1:8000/api'

  const state = ref<DesignerProfileOrganizationState | null>(null)
  const loading = ref(true)
  const saving = ref(false)
  const error = ref<string | null>(null)
  const validationErrors = ref<Record<string, string[]>>({})
  const logoUrl = ref<string | null>(null)

  // ─── helpers ────────────────────────────────────────────────────────────────

  /** قراءة HTTP status بشكل آمن من أي شكل خطأ */
  const getErrorStatus = (err: unknown): number | undefined => {
    const e = err as any
    return e?.status ?? e?.statusCode ?? e?.response?.status
  }

  const parseError = (err: unknown): string => {
    const status = getErrorStatus(err)
    if (status === 409) {
      return 'تم تعديل بيانات المنشأة في جلسة أخرى. حدّث البيانات ثم أعد المحاولة.'
    }
    const e = err as any
    return e?.data?.message ?? e?.response?._data?.message ?? 'حدث خطأ أثناء الاتصال بالخادم. حاول مرة أخرى.'
  }

  const clearError = () => {
    error.value = null
    validationErrors.value = {}
  }

  const disposeLogoUrl = () => {
    if (logoUrl.value) {
      URL.revokeObjectURL(logoUrl.value)
      logoUrl.value = null
    }
  }

  // ─── logo loader ─────────────────────────────────────────────────────────────

  const loadLogo = async () => {
    const headers: Record<string, string> = {
      Accept: 'image/*',
    }

    if (tokenCookie.value) {
      headers.Authorization = `Bearer ${tokenCookie.value}`
    }

    try {
      const blob = await $fetch<Blob>(`${baseUrl}/designer/profile/organization/logo/content`, {
        headers,
        responseType: 'blob',
      })
      disposeLogoUrl()
      logoUrl.value = URL.createObjectURL(blob)
    } catch {
      disposeLogoUrl()
    }
  }

  // ─── fetch ───────────────────────────────────────────────────────────────────

  /**
   * preserveError: إذا true لا يُمسح الخطأ الحالي (يُستخدم عند resync بعد 409).
   */
  const fetchOrganization = async (options?: { preserveError?: boolean }) => {
    loading.value = true
    if (!options?.preserveError) {
      clearError()
    }
    try {
      const response = await apiFetch<DesignerProfileOrganizationGetResponse>('/designer/profile/organization')
      state.value = response.data

      if (state.value.organization?.has_logo) {
        await loadLogo()
      } else {
        disposeLogoUrl()
      }

      return state.value
    } catch (err: unknown) {
      if (!options?.preserveError) {
        error.value = parseError(err)
      }
      disposeLogoUrl()
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * resync هادئ بعد 409 — يحدّث State دون مسح رسالة الخطأ الأصلية.
   */
  const syncAfterConflict = async () => {
    try {
      await fetchOrganization({ preserveError: true })
    } catch {
      // فشل GET الثانوي لا يستبدل خطأ الـ409 الأصلي
    }
  }

  // ─── save ────────────────────────────────────────────────────────────────────

  /**
   * يعيد updated_at الجديدة (non-null) بعد نجاح PUT.
   * يُحدِّث State محلياً من payload لأن Backend لا يعيد كائن Organization.
   */
  const saveOrganization = async (payload: DesignerProfileOrganizationPayload): Promise<string> => {
    saving.value = true
    clearError()
    try {
      const response = await apiFetch<DesignerProfileOrganizationMutationResponse>('/designer/profile/organization', {
        method: 'PUT',
        body: payload,
      })

      const newUpdatedAt = response.data.updated_at

      // تحديث State محلياً — Backend لا يعيد Organization object
      if (state.value) {
        state.value.updated_at = newUpdatedAt
        if (state.value.organization) {
          // تحديث حقول Organization الموجودة مع الإبقاء على has_logo
          state.value.organization.name = payload.organization_name
          state.value.organization.type = payload.organization_type
          state.value.organization.description = payload.description
          state.value.organization.website_url = payload.website_url
          state.value.organization.show_publicly = payload.show_publicly
        } else {
          // إنشاء أول مرة
          state.value.organization = {
            name: payload.organization_name,
            type: payload.organization_type,
            description: payload.description,
            website_url: payload.website_url,
            show_publicly: payload.show_publicly,
            has_logo: false,
          }
        }
      } else {
        // state نفسها null — بناء من الصفر
        state.value = {
          updated_at: newUpdatedAt,
          organization: {
            name: payload.organization_name,
            type: payload.organization_type,
            description: payload.description,
            website_url: payload.website_url,
            show_publicly: payload.show_publicly,
            has_logo: false,
          },
        }
      }

      return newUpdatedAt
    } catch (err: unknown) {
      const status = getErrorStatus(err)
      if (status === 422) {
        const e = err as any
        validationErrors.value = e?.data?.errors ?? e?.response?._data?.errors ?? {}
      } else if (status === 409) {
        error.value = parseError(err)
        await syncAfterConflict()
      } else {
        error.value = parseError(err)
      }
      throw err
    } finally {
      saving.value = false
    }
  }

  // ─── upload logo ──────────────────────────────────────────────────────────────

  /**
   * expectedUpdatedAt: string (non-null) — token من نتيجة saveOrganization.
   */
  const uploadLogo = async (file: File, expectedUpdatedAt: string): Promise<void> => {
    saving.value = true
    clearError()
    try {
      const formData = new FormData()
      formData.append('logo', file)
      // دائماً — لا يوجد if guard
      formData.append('expected_updated_at', expectedUpdatedAt)

      const response = await apiFetch<{ data: { updated_at: string } }>('/designer/profile/organization/logo', {
        method: 'POST',
        body: formData,
      })

      if (state.value) {
        state.value.updated_at = response.data.updated_at
        if (state.value.organization) {
          state.value.organization.has_logo = true
        }
      }
      await loadLogo()
    } catch (err: unknown) {
      const status = getErrorStatus(err)
      if (status === 422) {
        const e = err as any
        validationErrors.value = e?.data?.errors ?? e?.response?._data?.errors ?? {}
      } else if (status === 409) {
        error.value = parseError(err)
        await syncAfterConflict()
      } else {
        error.value = parseError(err)
      }
      throw err
    } finally {
      saving.value = false
    }
  }

  // ─── delete logo ──────────────────────────────────────────────────────────────

  /**
   * expectedUpdatedAt: string (non-null).
   * Backend قد يعيد changed=false إذا لم يكن هناك شعار.
   */
  const deleteLogo = async (expectedUpdatedAt: string): Promise<void> => {
    saving.value = true
    clearError()
    try {
      const response = await apiFetch<DesignerProfileOrganizationLogoDeleteResponse>('/designer/profile/organization/logo', {
        method: 'DELETE',
        body: { expected_updated_at: expectedUpdatedAt },
      })

      // response.data.changed — وليس response.changed
      if (response.data.changed) {
        const updatedAt = (response.data as { changed: true; updated_at: string }).updated_at
        if (state.value) {
          state.value.updated_at = updatedAt
          if (state.value.organization) {
            state.value.organization.has_logo = false
          }
        }
        disposeLogoUrl()
      }
      // changed=false: لا تغيير في Version ولا في has_logo
    } catch (err: unknown) {
      const status = getErrorStatus(err)
      if (status === 409) {
        error.value = parseError(err)
        await syncAfterConflict()
      } else {
        error.value = parseError(err)
      }
      throw err
    } finally {
      saving.value = false
    }
  }

  // ─── delete organization ──────────────────────────────────────────────────────

  const deleteOrganization = async (expectedUpdatedAt: string): Promise<void> => {
    saving.value = true
    clearError()
    try {
      await apiFetch('/designer/profile/organization', {
        method: 'DELETE',
        body: { expected_updated_at: expectedUpdatedAt },
      })

      state.value = {
        organization: null,
        updated_at: null,
      }
      disposeLogoUrl()
    } catch (err: unknown) {
      const status = getErrorStatus(err)
      if (status === 409) {
        error.value = parseError(err)
        await syncAfterConflict()
      } else {
        error.value = parseError(err)
      }
      throw err
    } finally {
      saving.value = false
    }
  }

  return {
    organizationState: readonly(state),
    logoUrl: readonly(logoUrl),
    loading: readonly(loading),
    saving: readonly(saving),
    error: readonly(error),
    validationErrors: readonly(validationErrors),
    fetchOrganization,
    saveOrganization,
    deleteOrganization,
    uploadLogo,
    deleteLogo,
    loadLogo,
    clearError,
    disposeLogoUrl,
  }
}
