import type {
  DesignerAvailability,
  DesignerApiResponse,
  DesignerProfilePublicationStatus,
} from '~/types/designer-profile'
import type {
  DesignerLanguageLevel,
  DesignerProfessionalLevel,
} from '~/types/designer-profile-professional'

export type DesignerPublicationBlockerSection = 'basic' | 'media' | 'professional' | 'account'
export type DesignerPublicationBlockerAction = 'edit_basic' | 'edit_avatar' | 'edit_professional' | 'contact_support'

export interface DesignerPublicationBlocker {
  code: string
  section: DesignerPublicationBlockerSection
  message: string
  action: DesignerPublicationBlockerAction
}

export interface DesignerProfilePublicationState {
  expected_updated_at: string
  publication: {
    status: DesignerProfilePublicationStatus
    published_at: string | null
    hidden_at: string | null
    updated_at: string
  }
  readiness: {
    ready: boolean
    completed: number
    total: 11
    blockers: DesignerPublicationBlocker[]
  }
  actions: {
    can_preview: boolean
    can_publish: boolean
    can_hide: boolean
  }
}

export interface DesignerProfilePublicationActionState extends DesignerProfilePublicationState {
  changed: boolean
}

export interface DesignerPreviewIdentity {
  username: string | null
  display_name: string
  professional_title: string | null
  primary_specialty: string | null
  bio: string | null
  avatar_url: string | null
  cover_url: string | null
  cover_focal_point: { x: number, y: number }
}

export interface DesignerPreviewSpecialty { name: string }
export interface DesignerPreviewProfessionalItem {
  name: string
  level: DesignerProfessionalLevel | DesignerLanguageLevel
}

export type DesignerPreviewAvailabilitySection =
  | { visible: false }
  | { visible: true, value: DesignerAvailability }

export type DesignerPreviewSpecialtiesSection =
  | { visible: false }
  | {
    visible: true
    service: DesignerPreviewSpecialty[]
    style: DesignerPreviewSpecialty[]
  }

export type DesignerPreviewItemsSection =
  | { visible: false }
  | { visible: true, items: DesignerPreviewProfessionalItem[] }

export type DesignerPreviewExperienceSection =
  | { visible: false }
  | { visible: true, years_of_experience: number | null }

export interface DesignerProfilePreview {
  identity: DesignerPreviewIdentity
  publication: {
    status: DesignerProfilePublicationStatus
    is_publicly_visible: boolean
    preview_mode: true
  }
  professional: {
    sections: {
      availability: DesignerPreviewAvailabilitySection
      specialties: DesignerPreviewSpecialtiesSection
      skills: DesignerPreviewItemsSection
      tools: DesignerPreviewItemsSection
      languages: DesignerPreviewItemsSection
      experience: DesignerPreviewExperienceSection
    }
    additional_information?: {
      professional_note: string
    }
  }
}

export type DesignerProfilePublicationResponse = DesignerApiResponse<DesignerProfilePublicationState>
export type DesignerProfilePublicationActionResponse = DesignerApiResponse<DesignerProfilePublicationActionState>
export type DesignerProfilePreviewResponse = DesignerApiResponse<{ preview: DesignerProfilePreview }>
