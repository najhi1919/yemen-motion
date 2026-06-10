<template>
    <div ref="canvasContainer" class="particle-canvas" aria-hidden="true"></div>
</template>

<script>
import * as THREE from 'three';

export default {
    name: 'ParticleBackground',
    emits: ['ready'],
    data() {
        return {
            scene: null,
            camera: null,
            renderer: null,
            particles: null,
            initialPositions: null,
            shapes: [],
            animationId: null,
            mouseX: 0,
            mouseY: 0,
            isMobile: false,
            isMounted: true,
        };
    },
    mounted() {
        this.isMobile = window.innerWidth < 768;
        this.mouseX = window.innerWidth / 2;
        this.mouseY = window.innerHeight / 2;
        this.initScene();
        this.createParticles();
        this.createShapes();
        this.animate();
        window.addEventListener('resize', this.handleResize);
        document.addEventListener('visibilitychange', this.handleVisibility);
    },
    beforeUnmount() {
        this.isMounted = false;
        if (this.animationId) cancelAnimationFrame(this.animationId);
        window.removeEventListener('resize', this.handleResize);
        document.removeEventListener('visibilitychange', this.handleVisibility);
        if (this.scene) {
            this.scene.traverse(obj => {
                if (obj.geometry) obj.geometry.dispose();
                if (obj.material) {
                    if (Array.isArray(obj.material)) obj.material.forEach(m => m.dispose());
                    else obj.material.dispose();
                }
            });
        }
        if (this.renderer) {
            this.renderer.dispose();
            if (this.$el) this.$el.innerHTML = '';
        }
    },
    methods: {
        initScene() {
            if (!this.$refs.canvasContainer || !this.isMounted) return;
            const w = window.innerWidth;
            const h = window.innerHeight;

            this.scene = new THREE.Scene();
            this.scene.fog = new THREE.FogExp2(0x05020A, 0.0007);

            this.camera = new THREE.PerspectiveCamera(75, w / h, 0.1, 1000);
            this.camera.position.z = 25;

            this.renderer = new THREE.WebGLRenderer({
                alpha: true,
                antialias: true,
                powerPreference: 'high-performance',
            });
            this.renderer.setSize(w, h);
            this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
            this.$refs.canvasContainer.appendChild(this.renderer.domElement);

            const ambient = new THREE.AmbientLight(0x6D28D9, 0.42);
            this.scene.add(ambient);

            const light1 = new THREE.PointLight(0xA855F7, 5, 80);
            light1.position.set(18, 14, 18);
            this.scene.add(light1);

            const light2 = new THREE.PointLight(0x22D3EE, 3.6, 80);
            light2.position.set(-18, -12, 18);
            this.scene.add(light2);

            const light3 = new THREE.PointLight(0xFB7185, 2.4, 70);
            light3.position.set(0, 18, -8);
            this.scene.add(light3);
        },

        createParticles() {
            if (!this.scene) return;
            const count = this.isMobile ? 420 : 1700;
            const positions = new Float32Array(count * 3);
            const colors = new Float32Array(count * 3);

            const palette = [
                new THREE.Color(0xA855F7),
                new THREE.Color(0x22D3EE),
                new THREE.Color(0xFB7185),
                new THREE.Color(0xFBBF24),
                new THREE.Color(0xC084FC),
            ];

            for (let i = 0; i < count; i++) {
                 const radius = 24 + Math.random() * 66;
                const theta = Math.random() * Math.PI * 2;
                const phi = Math.acos(2 * Math.random() - 1);
                positions[i * 3] = radius * Math.sin(phi) * Math.cos(theta);
                positions[i * 3 + 1] = radius * Math.sin(phi) * Math.sin(theta);
                positions[i * 3 + 2] = radius * Math.cos(phi) - 24;
                const c = palette[Math.floor(Math.random() * palette.length)];
                colors[i * 3] = c.r;
                colors[i * 3 + 1] = c.g;
                colors[i * 3 + 2] = c.b;
            }

            this.initialPositions = new Float32Array(positions);
            const geo = new THREE.BufferGeometry();
            geo.setAttribute('position', new THREE.BufferAttribute(positions, 3));
            geo.setAttribute('color', new THREE.BufferAttribute(colors, 3));

            const mat = new THREE.PointsMaterial({
                size: this.isMobile ? 0.16 : 0.32,
                vertexColors: true,
                transparent: true,
                opacity: 0.86,
                blending: THREE.AdditiveBlending,
                sizeAttenuation: true,
                depthWrite: false,
            });

            this.particles = new THREE.Points(geo, mat);
            this.scene.add(this.particles);
        },

        createShapes() {
            if (!this.scene) return;
            const geos = [
                new THREE.TorusGeometry(2.6, 0.42, 24, 64),
                new THREE.IcosahedronGeometry(2.0, 1),
                new THREE.OctahedronGeometry(2.1),
                new THREE.TorusKnotGeometry(1.8, 0.26, 96, 12),
            ];
            const colors = [0xA855F7, 0x22D3EE, 0xFBBF24, 0xFB7185];

            geos.forEach((geo, i) => {
                const mesh = new THREE.Mesh(geo, new THREE.MeshPhongMaterial({
                    color: colors[i],
                    emissive: colors[i],
                    emissiveIntensity: 0.15,
                    transparent: true,
                    opacity: this.isMobile ? 0.18 : 0.42,
                    wireframe: i % 2 === 0,
                    shininess: 100,
                }));
                mesh.position.set(
                    (Math.random() - 0.5) * 54,
                    (Math.random() - 0.5) * 36,
                    (Math.random() - 0.5) * 24 - 12
                );
                mesh.userData = {
                    rotSpeed: {
                        x: (Math.random() - 0.5) * 0.008,
                        y: (Math.random() - 0.5) * 0.008,
                    },
                    floatSpeed: Math.random() * 0.3 + 0.2,
                    floatAmp: this.isMobile ? 0.6 : 2.2,
                    initY: mesh.position.y,
                    initX: mesh.position.x,
                };
                this.scene.add(mesh);
                this.shapes.push(mesh);
            });
        },

        animate() {
            if (!this.isMounted) return;
            this.animationId = requestAnimationFrame(this.animate);

            const t = Date.now() * 0.0005;
            const mx = (this.mouseX / window.innerWidth) * 2 - 1;
            const my = -(this.mouseY / window.innerHeight) * 2 + 1;

            // Update particles
            if (this.particles && this.initialPositions) {
                const pos = this.particles.geometry.attributes.position.array;
                const init = this.initialPositions;
                const cx = mx * 15;
                const cy = my * 10;
                for (let i = 0; i < pos.length / 3; i++) {
                    const idx = i * 3;
                    const ix = init[idx], iy = init[idx + 1], iz = init[idx + 2];
                    const dx = ix - cx, dy = iy - cy;
                    const dist = Math.sqrt(dx * dx + dy * dy);
                    const repel = Math.max(0, 1 - dist / 30);
                    pos[idx] = ix + Math.sin(t + iy * 0.01) * 1.5 + dx * repel * 2;
                    pos[idx + 1] = iy + Math.cos(t * 0.8 + ix * 0.01) * 1.5 + dy * repel * 2;
                    pos[idx + 2] = iz + Math.sin(t * 0.6 + i * 0.001) * 1;
                }
                this.particles.geometry.attributes.position.needsUpdate = true;
                this.particles.rotation.y += 0.00042;
                this.particles.rotation.x += 0.00008;
            }

            // Update shapes
            this.shapes.forEach(m => {
                m.rotation.x += m.userData.rotSpeed.x;
                m.rotation.y += m.userData.rotSpeed.y;
                m.position.y = m.userData.initY + Math.sin(t * m.userData.floatSpeed) * m.userData.floatAmp;
                m.position.x = m.userData.initX + Math.cos(t * m.userData.floatSpeed * 0.7) * m.userData.floatAmp * 0.5;
            });

            // Camera follows mouse subtly
            this.camera.position.x += (mx * 1.2 - this.camera.position.x) * 0.03;
            this.camera.position.y += (my * 0.8 - this.camera.position.y) * 0.03;
            this.camera.lookAt(this.scene.position);

            this.renderer.render(this.scene, this.camera);
        },

        handleMouseMove(x, y) {
            this.mouseX = x;
            this.mouseY = y;
        },

        handleResize() {
            if (!this.camera || !this.renderer) return;
            const w = window.innerWidth;
            const h = window.innerHeight;
            this.camera.aspect = w / h;
            this.camera.updateProjectionMatrix();
            this.renderer.setSize(w, h);
        },

        handleVisibility() {
            if (document.hidden && this.animationId) {
                cancelAnimationFrame(this.animationId);
                this.animationId = null;
            } else if (!document.hidden && !this.animationId && this.isMounted) {
                this.animate();
            }
        },
    },
};
</script>

<style scoped>
.particle-canvas {
    position: absolute;
    inset: 0;
    z-index: 1;
    pointer-events: none;
}
</style>
