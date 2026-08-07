export type DesignerProfileOrganizationType =
  | 'studio'
  | 'agency'
  | 'company'
  | 'brand'
  | 'other'

export interface DesignerProfileOrganization {
  name: string
  type: DesignerProfileOrganizationType
  description: string | null
  has_logo: boolean
  website_url: string | null
  show_publicly: boolean
}

export interface DesignerProfileOrganizationState {
  organization: DesignerProfileOrganization | null
  updated_at: string | null
}

/** قيم النموذج بدون Version Token — يُرسَل من Drawer */
export interface DesignerProfileOrganizationInput {
  organization_name: string
  organization_type: DesignerProfileOrganizationType
  description: string | null
  website_url: string | null
  show_publicly: boolean
}

/** Payload نهائي يضاف له Token في الـPage — يُرسَل إلى API */
export interface DesignerProfileOrganizationPayload extends DesignerProfileOrganizationInput {
  expected_updated_at: string | null
}

/** استجابة GET /designer/profile/organization */
export interface DesignerProfileOrganizationGetResponse {
  success: boolean
  data: DesignerProfileOrganizationState
}

/** استجابة PUT /designer/profile/organization */
export interface DesignerProfileOrganizationMutationResponse {
  success: boolean
  message: string
  data: {
    changed: boolean
    updated_at: string
  }
}

/** استجابة DELETE /designer/profile/organization/logo */
export type DesignerProfileOrganizationLogoDeleteResponse =
  | { data: { changed: false } }
  | { data: { changed: true; updated_at: string } }
