export interface DesignerProfileFeaturedWork {
  id: number
  public_code: string
  title: string
  slug: string
  summary: string | null
  status: string
  media_type: 'image' | 'video' | 'gallery' | null
  created_at: string | null
  updated_at: string | null
  archive_state: {
    is_archived: boolean
    can_archive: boolean
    can_restore: boolean
    archived_at: string | null
    restore_target_status: string | null
    restore_target_visibility: string | null
  }
  category: {
    id: number
    name_ar: string
    name_en: string
    slug: string
  } | null
  tags: Array<{
    id: number
    name_ar: string
    name_en: string
    slug: string
  }>
  cover_presentation: {
    display_mode: 'fill' | 'fit'
    focal_point: {
      x: number
      y: number
    }
  }
  cover_media: {
    id: number
    kind: 'image' | 'video'
    processing_status: string
    content_url: string
    poster_url: string | null
  } | null
}

export interface DesignerProfileFeaturedWorksEnvelope {
  changed: boolean
  expected_updated_at: string
  limit: number
  selected: DesignerProfileFeaturedWork[]
  eligible: DesignerProfileFeaturedWork[]
}

export interface DesignerProfileFeaturedWorksResponse {
  success: true
  message: string
  data: DesignerProfileFeaturedWorksEnvelope
  errors: Record<string, string[]> | null
}
