import type { CSSProperties } from 'vue'
import type { DesignerWorkCoverDisplayMode } from '~/types/designer-work'

const clamp = (value: unknown): number => {
  const numeric = typeof value === 'number' && Number.isFinite(value) ? value : 50
  return Math.max(0, Math.min(100, numeric))
}

export const getDesignerWorkCoverStyle = (
  displayMode: DesignerWorkCoverDisplayMode | string | null | undefined,
  focalPoint: { x?: number | null, y?: number | null } | null | undefined,
): CSSProperties => {
  if (displayMode === 'fit') {
    return {
      objectFit: 'contain',
      objectPosition: '50% 50%',
    }
  }

  return {
    objectFit: 'cover',
    objectPosition: `${clamp(focalPoint?.x)}% ${clamp(focalPoint?.y)}%`,
  }
}
