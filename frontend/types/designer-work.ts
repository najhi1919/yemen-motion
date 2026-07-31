export type DesignerWorkGroup = 'all' | 'draft' | 'review' | 'changes' | 'published' | 'closed' | 'archived'
export type DesignerWorkSort = 'updated_at' | 'created_at' | 'title'
export type DesignerWorkDirection = 'asc' | 'desc'
export type DesignerWorkCoverDisplayMode = 'fill' | 'fit'

export interface DesignerWorkCoverMedia {
  id: number
  kind: 'image' | 'video'
  processing_status: string
  content_url: string
  poster_url: string | null
}

export interface DesignerWorkTaxonomySummary {
  id: number
  name_ar: string
  name_en: string | null
  slug: string
}

export interface DesignerWorkArchiveState {
  is_archived: boolean
  can_archive: boolean
  can_restore: boolean
  archived_at: string | null
  restore_target_status: string | null
  restore_target_visibility: 'public' | 'hidden' | null
}

export interface DesignerWork {
  id: number
  public_code: string
  title: string
  slug: string
  summary: string | null
  status: string
  media_type: string
  created_at: string
  updated_at: string
  archive_state: DesignerWorkArchiveState
  category: DesignerWorkTaxonomySummary | null
  tags: DesignerWorkTaxonomySummary[]
  cover_presentation: {
    display_mode: DesignerWorkCoverDisplayMode
    focal_point: {
      x: number
      y: number
    }
  }
  cover_media: DesignerWorkCoverMedia | null
}

export interface DesignerWorksSummary {
  total: number
  draft: number
  review: number
  changes: number
  published: number
  closed: number
  archived: number
}

export type DesignerWorkLifecycleAction = 'archive' | 'restore'

export interface DesignerWorkLifecycleResponse {
  data: {
    changed: boolean
    action: DesignerWorkLifecycleAction
    previous_status: string
    work: DesignerWork
  }
}

export interface DesignerWorksMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

export interface DesignerWorksResponse {
  data: DesignerWork[]
  summary: DesignerWorksSummary
  meta: DesignerWorksMeta
  applied_filters: {
    q: string | null
    group: DesignerWorkGroup
    sort: DesignerWorkSort
    direction: DesignerWorkDirection
  }
}
