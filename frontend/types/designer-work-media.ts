export type DesignerWorkMediaKind = 'image' | 'video'
export type DesignerWorkMediaProcessingStatus = 'pending' | 'ready' | 'failed'

export interface DesignerMediaWork {
  id: number
  status: string
  media_type: 'image' | 'video' | 'gallery' | null
  cover_media_id: number | null
}

export interface DesignerWorkMedia {
  id: number
  kind: DesignerWorkMediaKind
  original_name: string
  mime_type: string
  extension: string
  size_bytes: number
  position: number
  width: number | null
  height: number | null
  duration_ms: number | null
  processing_status: DesignerWorkMediaProcessingStatus
  processing_stage: string
  processing_progress: number
  processing_started_at: string | null
  processing_completed_at: string | null
  processing_attempts: number
  processing_message: string
  can_retry_processing: boolean
  is_cover: boolean
  created_at: string | null
  updated_at: string | null
  content_url: string
  poster_url: string | null
}

export interface DesignerWorkMediaPolicy {
  source: string
  settings_version: number
  work_media_type: string | null
  allowed_media_types: string[]
  allowed_file_kinds: string[]
  allowed_mime_types: string[]
  configured_limits: {
    max_items: number | null
    max_file_size_kb: number | null
  }
  effective_limits: {
    max_items: number | null
    max_file_size_kb: number | null
  }
  effective_max_file_size_kb?: number | null
  enforcement: Record<string, boolean>
}

export interface DesignerWorkMediaCounts {
  active: number
  remaining: number | null
}

export interface DesignerWorkMediaIndexResponse {
  data: {
    work: DesignerMediaWork
    media: DesignerWorkMedia[]
    media_policy: DesignerWorkMediaPolicy
    counts: DesignerWorkMediaCounts
    media_state: {
      editable: boolean
      allowed_statuses: string[]
    }
  }
}

export interface DesignerWorkMediaMutationResponse<T> {
  data: T
  message: string
}

export interface DesignerWorkMediaPreview {
  item: DesignerWorkMedia
  url: string
  loading: boolean
  opener: HTMLElement | null
}

export interface DesignerWorkVideoCoverDialogState {
  item: DesignerWorkMedia | null
  video_url: string | null
  loading: boolean
  opener: HTMLElement | null
}
