import type { DesignerWorkCoverDisplayMode } from './designer-work'

export interface DesignerWorkPresentationForm {
  cover_display_mode: DesignerWorkCoverDisplayMode
  focal_x: number
  focal_y: number
}

export interface DesignerWorkPresentationCurrent {
  id: number
  public_code: string
  title: string
  status: string
  media_type: string | null
  cover_display_mode: DesignerWorkCoverDisplayMode
  cover_focal_point: {
    x: number
    y: number
  }
}

export interface DesignerWorkPresentationState {
  editable: boolean
  available_modes: DesignerWorkCoverDisplayMode[]
}

export interface DesignerWorkPresentationShowResponse {
  data: {
    work: DesignerWorkPresentationCurrent
    presentation_state: DesignerWorkPresentationState
  }
}

export interface DesignerWorkPresentationUpdateResponse
  extends DesignerWorkPresentationShowResponse {
  data: DesignerWorkPresentationShowResponse['data'] & {
    changed: boolean
    changed_keys: Array<'cover_display_mode' | 'cover_focal_point'>
  }
  message: string
}

export interface DesignerWorkPresentationCover {
  id: number
  kind: 'image' | 'video'
  processing_status: string
  url: string | null
}
