type ThreeModule = typeof import('three')
type QualityLevel = 600 | 300 | 150 | 250

interface ParticleOptions {
  THREE: ThreeModule
  scene: InstanceType<ThreeModule['Scene']>
  reducedMotion?: boolean
}

const dynamicVertexShader = `
attribute vec3 instanceOffset;
attribute float instanceScale;
attribute float instancePhase;
uniform float uTime;
uniform vec2 uMouse;
uniform float uMouseMode;
uniform float uVisibleCount;
varying float vAlpha;

void main() {
  vec3 transformed = position * instanceScale + instanceOffset;
  float indexGate = step(float(gl_InstanceID), uVisibleCount);
  float wave = sin(uTime * 0.65 + instancePhase + transformed.x * 0.06) * 0.32;
  vec2 delta = transformed.xy - (uMouse * vec2(16.0, 10.0));
  float distanceToMouse = max(length(delta), 0.001);
  float force = smoothstep(8.0, 0.0, distanceToMouse) * uMouseMode;
  vec2 direction = normalize(delta);
  transformed.xy += direction * force * 1.8;
  transformed.z += wave + force * 0.8;
  vAlpha = indexGate * (0.28 + 0.45 * smoothstep(16.0, 2.0, abs(transformed.z)));
  gl_Position = projectionMatrix * modelViewMatrix * instanceMatrix * vec4(transformed, 1.0);
}
`

const staticVertexShader = `
attribute vec3 instanceOffset;
attribute float instanceScale;
uniform vec2 uMouse;
uniform float uMouseMode;
uniform float uVisibleCount;
varying float vAlpha;

void main() {
  vec3 transformed = position * instanceScale + instanceOffset;
  float indexGate = step(float(gl_InstanceID), uVisibleCount);
  vec2 delta = transformed.xy - (uMouse * vec2(16.0, 10.0));
  float distanceToMouse = max(length(delta), 0.001);
  float force = smoothstep(6.0, 0.0, distanceToMouse) * uMouseMode;
  transformed.xy += normalize(delta) * force * 0.7;
  vAlpha = indexGate * 0.42;
  gl_Position = projectionMatrix * modelViewMatrix * instanceMatrix * vec4(transformed, 1.0);
}
`

const fragmentShader = `
precision mediump float;
uniform vec3 uColor;
varying float vAlpha;

void main() {
  gl_FragColor = vec4(uColor, vAlpha);
}
`

const isWeakDevice = () => {
  const nav = navigator as Navigator & { deviceMemory?: number }
  return (nav.deviceMemory || 4) <= 4 || (navigator.hardwareConcurrency || 4) <= 4
}

const getInitialQuality = (): QualityLevel => {
  if (typeof window === 'undefined') return 150
  if (window.innerWidth >= 1024) return 600
  if (window.innerWidth >= 768) return 300
  return 150
}

const qualityFromFps = (fps: number): QualityLevel => {
  if (fps > 55) return 600
  if (fps >= 45) return 300
  if (fps >= 30) return 150
  return 250
}

export function useParticles(options: ParticleOptions) {
  const { THREE } = options
  const staticMode = isWeakDevice() || options.reducedMotion
  const maxParticles = 600
  const geometry = new THREE.IcosahedronGeometry(0.035, 0)
  const material = new THREE.ShaderMaterial({
    transparent: true,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    uniforms: {
      uTime: { value: 0 },
      uMouse: { value: new THREE.Vector2(0, 0) },
      uMouseMode: { value: 1 },
      uVisibleCount: { value: staticMode ? 150 : getInitialQuality() },
      uColor: { value: new THREE.Color('#7dd3fc') }
    },
    vertexShader: staticMode ? staticVertexShader : dynamicVertexShader,
    fragmentShader
  })

  const mesh = new THREE.InstancedMesh(geometry, material, maxParticles)
  const offsets = new Float32Array(maxParticles * 3)
  const scales = new Float32Array(maxParticles)
  const phases = new Float32Array(maxParticles)
  const matrix = new THREE.Matrix4()

  for (let index = 0; index < maxParticles; index += 1) {
    offsets[index * 3] = (Math.random() - 0.5) * 34
    offsets[index * 3 + 1] = (Math.random() - 0.5) * 20
    offsets[index * 3 + 2] = (Math.random() - 0.5) * 24
    scales[index] = 0.55 + Math.random() * 1.4
    phases[index] = Math.random() * Math.PI * 2
    mesh.setMatrixAt(index, matrix)
  }

  geometry.setAttribute('instanceOffset', new THREE.InstancedBufferAttribute(offsets, 3))
  geometry.setAttribute('instanceScale', new THREE.InstancedBufferAttribute(scales, 1))
  geometry.setAttribute('instancePhase', new THREE.InstancedBufferAttribute(phases, 1))
  options.scene.add(mesh)

  let frames = 0
  let lastFpsMark = performance.now()
  let currentQuality = material.uniforms.uVisibleCount.value as QualityLevel

  function setMouse(x: number, y: number, mode: 'attract' | 'repel' = 'repel') {
    material.uniforms.uMouse.value.set(x, y)
    material.uniforms.uMouseMode.value = mode === 'repel' ? 1 : -0.65
  }

  function update(elapsed: number) {
    material.uniforms.uTime.value = staticMode ? 0 : elapsed
    if (staticMode) return

    frames += 1
    const now = performance.now()
    if (now - lastFpsMark >= 1000) {
      const fps = (frames * 1000) / (now - lastFpsMark)
      const nextQuality = qualityFromFps(fps)
      if (nextQuality !== currentQuality) {
        currentQuality = nextQuality
        material.uniforms.uVisibleCount.value = nextQuality
      }
      frames = 0
      lastFpsMark = now
    }
  }

  function dispose() {
    options.scene.remove(mesh)
    geometry.dispose()
    material.dispose()
  }

  return { mesh, update, setMouse, dispose }
}
