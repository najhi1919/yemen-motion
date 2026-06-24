type IdleCallbackHandle = number
type ThreeModule = typeof import('three')
type SceneContext = {
  THREE: ThreeModule
  scene: InstanceType<ThreeModule['Scene']>
  camera: InstanceType<ThreeModule['PerspectiveCamera']>
  renderer: InstanceType<ThreeModule['WebGLRenderer']>
}

interface SceneOptions {
  canvas: HTMLCanvasElement
  reducedMotion?: boolean
  onFrame?: (delta: number, elapsed: number) => void
  onReady?: (context: SceneContext) => void | Promise<void>
}

const scheduleIdle = (callback: () => void) => {
  if (typeof window === 'undefined') return 0
  const requestIdle = window.requestIdleCallback || ((cb: IdleRequestCallback) => window.setTimeout(() => cb({ didTimeout: false, timeRemaining: () => 0 }), 1000))
  return requestIdle(callback) as IdleCallbackHandle
}

const cancelIdle = (handle: IdleCallbackHandle) => {
  if (!handle || typeof window === 'undefined') return
  const cancel = window.cancelIdleCallback || window.clearTimeout
  cancel(handle)
}

export function useThreeScene() {
  let THREE: ThreeModule | null = null
  let scene: SceneContext['scene'] | null = null
  let camera: SceneContext['camera'] | null = null
  let renderer: SceneContext['renderer'] | null = null
  let frameId = 0
  let idleId = 0
  let resizeHandler: (() => void) | null = null
  let visibilityHandler: (() => void) | null = null
  let running = false
  let startedAt = 0
  let lastFrameAt = 0

  function create({ canvas, reducedMotion = false, onFrame, onReady }: SceneOptions) {
    idleId = scheduleIdle(async () => {
      THREE = await import('three')
      scene = new THREE.Scene()
      camera = new THREE.PerspectiveCamera(55, canvas.clientWidth / Math.max(canvas.clientHeight, 1), 0.1, 120)
      camera.position.z = 28

      const context = canvas.getContext('webgl2', { alpha: true, antialias: false }) || canvas.getContext('webgl', { alpha: true, antialias: false })
      if (!context) {
        scene = null
        camera = null
        THREE = null
        return
      }

      renderer = new THREE.WebGLRenderer({
        canvas,
        context,
        alpha: true,
        antialias: false,
        powerPreference: 'high-performance'
      })
      renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.25))
      renderer.setClearColor(0x000000, 0)

      resizeHandler = () => {
        if (!camera || !renderer) return
        const width = canvas.clientWidth || window.innerWidth
        const height = canvas.clientHeight || window.innerHeight
        camera.aspect = width / Math.max(height, 1)
        camera.updateProjectionMatrix()
        renderer.setSize(width, height, false)
      }

      visibilityHandler = () => {
        running = document.visibilityState === 'visible' && !reducedMotion
        if (running) animate(onFrame)
      }

      window.addEventListener('resize', resizeHandler, { passive: true })
      document.addEventListener('visibilitychange', visibilityHandler)
      resizeHandler()
      await onReady?.({ THREE, scene, camera, renderer })

      running = !reducedMotion
      startedAt = performance.now()
      lastFrameAt = startedAt
      if (running) animate(onFrame)
    })
  }

  function animate(onFrame?: (delta: number, elapsed: number) => void) {
    if (!running || !renderer || !scene || !camera) return
    frameId = window.requestAnimationFrame(() => animate(onFrame))
    const now = performance.now()
    const delta = (now - lastFrameAt) / 1000
    const elapsed = (now - startedAt) / 1000
    lastFrameAt = now
    onFrame?.(delta, elapsed)
    renderer.render(scene, camera)
  }

  function destroy() {
    running = false
    cancelIdle(idleId)
    if (frameId) window.cancelAnimationFrame(frameId)
    if (resizeHandler) window.removeEventListener('resize', resizeHandler)
    if (visibilityHandler) document.removeEventListener('visibilitychange', visibilityHandler)

    scene?.traverse((object) => {
      const mesh = object as { geometry?: { dispose?: () => void }; material?: { dispose?: () => void } | Array<{ dispose?: () => void }> }
      mesh.geometry?.dispose?.()
      if (Array.isArray(mesh.material)) mesh.material.forEach((item) => item.dispose?.())
      else mesh.material?.dispose?.()
    })

    renderer?.dispose()
    scene = null
    camera = null
    renderer = null
    THREE = null
  }

  return { create, destroy }
}
