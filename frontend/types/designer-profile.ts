export type DesignerAvailability = 'available' | 'partially_available' | 'unavailable'

export interface DesignerIdentityMedia {
  avatar_url: string | null
  cover_url: string | null
  cover_focal_point: {
    x: number
    y: number
  }
}

export interface DesignerProfile {
  id: number
  display_name: string
  professional_title: string | null
  primary_specialty: string | null
  bio: string | null
  identity_media: DesignerIdentityMedia
  availability: DesignerAvailability
  publication_status: 'draft'
  published_at: string | null
  created_at: string | null
  updated_at: string | null
}

export interface BasicCompletion {
  completed: number
  total: 5
  percentage: number
  missing: string[]
}

export interface DesignerProfileEnvelope {
  profile: DesignerProfile | null
  username: string | null
  can_claim_username: boolean
  basic_completion: BasicCompletion
}

export interface DesignerProfilePayload {
  username?: string | null
  display_name: string
  professional_title: string | null
  primary_specialty: string | null
  bio: string | null
  availability?: DesignerAvailability
}

export interface UsernameAvailability {
  available: boolean
  normalized: string | null
  reason: 'invalid' | 'reserved' | 'taken' | null
}

export interface DesignerApiResponse<T> {
  success: boolean
  message: string
  data: T
  errors: Record<string, string[]> | null
}
