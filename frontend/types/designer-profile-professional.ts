import type { DesignerAvailability, DesignerApiResponse } from '~/types/designer-profile'

export type DesignerProfessionalLevel = 'beginner' | 'intermediate' | 'advanced' | 'expert'
export type DesignerLanguageLevel = 'basic' | 'conversational' | 'professional' | 'native'
export type DesignerSpecialtyKind = 'service' | 'occasion' | 'style'

export interface DesignerProfessionalVisibility {
  availability: boolean
  specialties: boolean
  skills: boolean
  tools: boolean
  languages: boolean
  experience: boolean
}

export interface DesignerProfessionalSpecialty { name: string }
export interface DesignerProfessionalSpecialties {
  service: DesignerProfessionalSpecialty[]
  occasion: DesignerProfessionalSpecialty[]
  style: DesignerProfessionalSpecialty[]
}
export interface DesignerProfessionalSkill { name: string, level: DesignerProfessionalLevel }
export interface DesignerProfessionalTool { name: string, level: DesignerProfessionalLevel }
export interface DesignerProfessionalLanguage { name: string, level: DesignerLanguageLevel }
export type DesignerProfessionalListItem = DesignerProfessionalSpecialty | DesignerProfessionalSkill | DesignerProfessionalTool | DesignerProfessionalLanguage

export interface DesignerProfileProfessionalData {
  years_of_experience: number | null
  professional_note: string | null
  availability: DesignerAvailability
  visibility: DesignerProfessionalVisibility
  specialties: DesignerProfessionalSpecialties
  skills: DesignerProfessionalSkill[]
  tools: DesignerProfessionalTool[]
  languages: DesignerProfessionalLanguage[]
  updated_at: string
}

export interface DesignerProfessionalCompletionSection { complete: boolean, count: number }
export interface DesignerProfessionalCompletion {
  completed: number
  total: 5
  percentage: number
  missing: string[]
  sections: {
    experience: DesignerProfessionalCompletionSection
    specialties: DesignerProfessionalCompletionSection
    skills: DesignerProfessionalCompletionSection
    tools: DesignerProfessionalCompletionSection
    languages: DesignerProfessionalCompletionSection
  }
}

export interface DesignerProfessionalOptions {
  availability: DesignerAvailability[]
  specialty_kinds: DesignerSpecialtyKind[]
  skill_levels: DesignerProfessionalLevel[]
  tool_levels: DesignerProfessionalLevel[]
  language_levels: DesignerLanguageLevel[]
}

export interface DesignerProfileProfessionalEnvelope {
  changed: boolean
  professional: DesignerProfileProfessionalData
  completion: DesignerProfessionalCompletion
  options: DesignerProfessionalOptions
}

export interface DesignerProfileProfessionalPayload {
  availability: DesignerAvailability
  years_of_experience: number | null
  professional_note: string | null
  visibility: DesignerProfessionalVisibility
  specialties: {
    service: string[]
    occasion: string[]
    style: string[]
  }
  skills: DesignerProfessionalSkill[]
  tools: DesignerProfessionalTool[]
  languages: DesignerProfessionalLanguage[]
}

export type DesignerProfileProfessionalResponse = DesignerApiResponse<DesignerProfileProfessionalEnvelope>
