export type PublicDesignerAvailability = 'available' | 'partially_available' | 'limited' | 'unavailable'
export type PublicProfessionalLevel =
  | 'beginner'
  | 'intermediate'
  | 'advanced'
  | 'expert'
  | 'basic'
  | 'conversational'
  | 'professional'
  | 'native'

export interface PublicVisibleSection {
  visible: false
}

export interface PublicDesignerIdentity {
  username: string
  display_name: string
  professional_title: string | null
  primary_specialty: string | null
  bio: string | null
  avatar_url: string | null
  cover_url: string | null
  cover_focal_point: { x: number, y: number }
}

export type PublicAvailabilitySection = PublicVisibleSection | {
  visible: true
  value: PublicDesignerAvailability
}

export interface PublicNamedItem { name: string }
export interface PublicLeveledItem extends PublicNamedItem { level: PublicProfessionalLevel }

export type PublicSpecialtiesSection = PublicVisibleSection | {
  visible: true
  service: PublicNamedItem[]
  style: PublicNamedItem[]
}

export type PublicItemsSection = PublicVisibleSection | {
  visible: true
  items: PublicLeveledItem[]
}

export type PublicExperienceSection = PublicVisibleSection | {
  visible: true
  years_of_experience: number | null
}

export interface PublicDesignerProfessional {
  sections: {
    availability: PublicAvailabilitySection
    specialties: PublicSpecialtiesSection
    skills: PublicItemsSection
    tools: PublicItemsSection
    languages: PublicItemsSection
    experience: PublicExperienceSection
  }
  additional_information?: { professional_note: string }
}

export type PublicDesignerOrganization =
  | { visible: false }
  | {
      visible: true
      name: string
      type: 'studio' | 'agency' | 'company' | 'brand' | 'other'
      description: string | null
      logo_url: string | null
      website_url: string | null
    }

export interface PublicDesignerWork {
  public_code: string
  slug: string
  title: string
  summary: string | null
  media_type: 'image' | 'video' | 'gallery' | null
  published_at: string | null
  category: {
    name_ar: string
    name_en: string
    slug: string
  } | null
  tags: Array<{
    name_ar: string
    name_en: string
    slug: string
  }>
  cover_presentation: {
    display_mode: 'fill' | 'fit'
    focal_point: { x: number, y: number }
  }
  cover_media: {
    kind: 'image' | 'video'
    width: number | null
    height: number | null
    duration_ms: number | null
    content_url: string
    poster_url: string | null
  } | null
}

export interface PublicDesignerProfile {
  identity: PublicDesignerIdentity
  professional: PublicDesignerProfessional
  organization: PublicDesignerOrganization
  published_at: string | null
  featured_works: {
    items: PublicDesignerWork[]
    total: number
  }
  works: {
    items: PublicDesignerWork[]
    total: number
  }
  seo: {
    title: string
    description: string
    canonical_path: string
    image_url: string | null
    type: 'profile'
  }
}

export interface PublicDesignerProfileResponse {
  success: true
  data: { profile: PublicDesignerProfile }
}
