type MorphPath = SVGPathElement | null | undefined

const fallbackSuccess = 'M100 12 C151 12 190 51 190 100 C190 151 151 190 100 190 C49 190 10 151 10 100 C10 51 49 12 100 12 Z'
const fallbackError = 'M100 22 C154 14 186 55 176 108 C166 164 121 192 69 176 C21 162 4 110 24 64 C37 34 63 18 100 22 Z'
const fallbackIdle = 'M100 18 C146 18 182 54 182 100 C182 146 146 182 100 182 C54 182 18 146 18 100 C18 54 54 18 100 18 Z'

const prefersReducedMotion = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches

export function useSvgMorph(path: MorphPath, options?: { successPath?: string; errorPath?: string; idlePath?: string }) {
  const idlePath = options?.idlePath || path?.getAttribute('d') || fallbackIdle
  const successPath = options?.successPath || fallbackSuccess
  const errorPath = options?.errorPath || fallbackError

  async function morphTo(targetPath: string) {
    if (!path) return undefined

    if (prefersReducedMotion()) {
      path.setAttribute('d', targetPath)
      return undefined
    }

    const [flubber, { gsap }] = await Promise.all([import('flubber'), import('gsap')])
    const fromPath = path.getAttribute('d') || idlePath
    const mixer = flubber.interpolate(fromPath, targetPath, { maxSegmentLength: 8 })
    const progress = { value: 0 }

    return gsap.to(progress, {
      value: 1,
      duration: 0.42,
      ease: 'power3.inOut',
      onUpdate: () => path.setAttribute('d', mixer(progress.value))
    })
  }

  return {
    morphToSuccess: () => morphTo(successPath),
    morphToError: () => morphTo(errorPath),
    morphToIdle: () => morphTo(idlePath)
  }
}
