type AnimationTarget = Element | HTMLElement | SVGElement | null | undefined

const prefersReducedMotion = () => typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
const loadGsap = async () => (await import('gsap')).gsap

export function useLoginAnimations() {
  const duration = (value: number) => (prefersReducedMotion() ? Math.min(value, 0.12) : value)

  async function cardEntrance(target: AnimationTarget) {
    if (!target) return undefined
    const gsap = await loadGsap()

    return gsap.fromTo(
      target,
      { autoAlpha: 0 },
      { autoAlpha: 1, duration: duration(0.35), ease: 'power2.out' }
    )
  }

  async function successAnimation(targets: AnimationTarget[]) {
    const activeTargets = targets.filter(Boolean) as Element[]
    if (!activeTargets.length) return undefined
    const gsap = await loadGsap()

    return gsap.timeline().to(activeTargets, {
      y: -8,
      scale: 1.015,
      duration: duration(0.24),
      ease: 'power2.out',
      stagger: 0.03
    }).to(activeTargets, {
      y: 0,
      scale: 1,
      duration: duration(0.28),
      ease: 'power2.out',
      stagger: 0.02
    })
  }

  async function errorShake(target: AnimationTarget) {
    if (!target) return undefined
    const gsap = await loadGsap()

    return gsap.fromTo(
      target,
      { x: 0 },
      { x: prefersReducedMotion() ? 0 : -8, duration: duration(0.07), repeat: prefersReducedMotion() ? 0 : 5, yoyo: true, ease: 'power1.inOut' }
    )
  }

  async function buttonRipple(target: AnimationTarget) {
    if (!target) return undefined
    const gsap = await loadGsap()

    return gsap.fromTo(target, { scale: 0.985 }, { scale: 1, duration: duration(0.2), ease: 'power2.out' })
  }

  async function logoHover(target: AnimationTarget) {
    if (!target) return undefined
    const gsap = await loadGsap()

    return gsap.to(target, { scale: 1.06, rotate: 2, duration: duration(0.22), ease: 'power2.out' })
  }

  async function logoLeave(target: AnimationTarget) {
    if (!target) return undefined
    const gsap = await loadGsap()

    return gsap.to(target, { scale: 1, rotate: 0, duration: duration(0.24), ease: 'power2.out' })
  }

  async function pageTransition(target: AnimationTarget) {
    if (!target) return Promise.resolve()
    const gsap = await loadGsap()

    return new Promise<void>((resolve) => {
      gsap.to(target, {
        autoAlpha: 0,
        y: -18,
        scale: 0.985,
        duration: duration(0.38),
        ease: 'power3.inOut',
        onComplete: resolve
      })
    })
  }

  return {
    cardEntrance,
    successAnimation,
    errorShake,
    buttonRipple,
    logoHover,
    logoLeave,
    pageTransition,
    prefersReducedMotion
  }
}
