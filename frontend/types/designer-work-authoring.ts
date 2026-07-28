export type DesignerWorkAuthoringStatus =
  | 'draft'
  | 'submitted'
  | 'in_review'
  | 'changes_requested'
  | 'approved'
  | 'published'
  | 'rejected'
  | 'hidden'
  | 'archived'

export type DesignerWorkMediaType = 'image' | 'video' | 'gallery'

export interface DesignerWorkAuthoring {
  id: number
  title: string
  slug: string
  summary: string | null
  description: string | null
  status: DesignerWorkAuthoringStatus
  visibility_status: string
  media_type: DesignerWorkMediaType | null
  price_amount: string | number | null
  delivery_days: number | null
  category_id: number | null
  cover_media_id: number | null
  created_at: string
  updated_at: string
}

export interface DesignerWorkAuthoringDraft {
  title: string
  summary: string
  description: string
  media_type: DesignerWorkMediaType | ''
  price_amount: string
  delivery_days: string
}

export interface DesignerWorkAuthoringResponse {
  data: {
    work: DesignerWorkAuthoring
    changed: boolean
    changed_keys: string[]
  }
  message: string
}

export interface DesignerWorkAuthoringShowResponse {
  data: {
    work: DesignerWorkAuthoring
    authoring_state: {
      editable: boolean
      allowed_statuses: DesignerWorkAuthoringStatus[]
    }
    authoring_policy: {
      allowed_media_types: DesignerWorkMediaType[]
    }
  }
}
