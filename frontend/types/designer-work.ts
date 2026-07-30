export type DesignerWorkGroup = 'all' | 'draft' | 'review' | 'changes' | 'published' | 'closed'
export type DesignerWorkSort = 'updated_at' | 'created_at' | 'title'
export type DesignerWorkDirection = 'asc' | 'desc'

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
  category: DesignerWorkTaxonomySummary | null
  tags: DesignerWorkTaxonomySummary[]
  cover_media: DesignerWorkCoverMedia | null
}

export interface DesignerWorksSummary {
  total: number
  draft: number
  review: number
  changes: number
  published: number
  closed: number
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
