export interface DesignerWorkTaxonomyOption {
  id: number
  name_ar: string
  name_en: string | null
  slug: string
  is_active: boolean
}

export interface DesignerWorkMetadataCurrent {
  id: number
  public_code: string
  status: string
  category_id: number | null
  tag_ids: number[]
  category: DesignerWorkTaxonomyOption | null
  tags: DesignerWorkTaxonomyOption[]
}

export interface DesignerWorkMetadataState {
  editable: boolean
  allowed_statuses: string[]
  max_tags: number
  category_tracking: {
    catalog_record_exists: boolean
    is_legacy_unmapped: boolean
    is_uncategorized: boolean
    is_disabled: boolean
  }
}

export interface DesignerWorkMetadataPayload {
  work: DesignerWorkMetadataCurrent
  options: {
    categories: DesignerWorkTaxonomyOption[]
    tags: DesignerWorkTaxonomyOption[]
  }
  metadata_state: DesignerWorkMetadataState
}

export interface DesignerWorkMetadataShowResponse {
  data: DesignerWorkMetadataPayload
}

export interface DesignerWorkMetadataUpdateResponse {
  data: DesignerWorkMetadataPayload & {
    changed: boolean
    changed_keys: string[]
  }
  message: string
}
