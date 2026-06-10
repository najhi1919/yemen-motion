import * as THREE from 'three';

export function useParticles(scene) {
    let particleSystem;
    let initialPositions;
    const particleCount = 1200;

    function createParticles() {
        const positions = new Float32Array(particleCount * 3);
        const colors = new Float32Array(particleCount * 3);
        const sizes = new Float32Array(particleCount);

        const palette = [
            new THREE.Color(0x7C3AED),
            new THREE.Color(0x2DD4BF),
            new THREE.Color(0xF59E0B),
            new THREE.Color(0xEC4899),
            new THREE.Color(0x8B5CF6),
        ];

        for (let i = 0; i < particleCount; i++) {
            const radius = 50 + Math.random() * 80;
            const theta = Math.random() * Math.PI * 2;
            const phi = Math.acos(2 * Math.random() - 1);

            positions[i * 3] = radius * Math.sin(phi) * Math.cos(theta);
            positions[i * 3 + 1] = radius * Math.sin(phi) * Math.sin(theta);
            positions[i * 3 + 2] = radius * Math.cos(phi) - 20;

            const color = palette[Math.floor(Math.random() * palette.length)];
            colors[i * 3] = color.r;
            colors[i * 3 + 1] = color.g;
            colors[i * 3 + 2] = color.b;

            sizes[i] = Math.random() * 2 + 0.5;
        }

        initialPositions = new Float32Array(positions);

        const geometry = new THREE.BufferGeometry();
        geometry.setAttribute('position', new THREE.BufferAttribute(positions, 3));
        geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3));

        const material = new THREE.PointsMaterial({
            size: 0.25,
            vertexColors: true,
            transparent: true,
            opacity: 0.7,
            blending: THREE.AdditiveBlending,
            sizeAttenuation: true,
            depthWrite: false,
        });

        particleSystem = new THREE.Points(geometry, material);
        scene.add(particleSystem);
    }

    function updateParticles({ mouseX, mouseY, time }) {
        if (!particleSystem) return;

        const positions = particleSystem.geometry.attributes.position.array;
        const centerX = mouseX * 15;
        const centerY = mouseY * 10;

        for (let i = 0; i < particleCount; i++) {
            const idx = i * 3;
            const ix = initialPositions[idx];
            const iy = initialPositions[idx + 1];
            const iz = initialPositions[idx + 2];

            const waveX = Math.sin(time + iy * 0.01) * 2;
            const waveY = Math.cos(time * 0.8 + ix * 0.01) * 2;
            const waveZ = Math.sin(time * 0.6 + i * 0.001) * 1.5;

            // Distance from mouse
            const dx = ix - centerX;
            const dy = iy - centerY;
            const dist = Math.sqrt(dx * dx + dy * dy);
            const repelStrength = Math.max(0, 1 - dist / 30);

            positions[idx] = ix + waveX + dx * repelStrength * 2;
            positions[idx + 1] = iy + waveY + dy * repelStrength * 2;
            positions[idx + 2] = iz + waveZ;
        }

        particleSystem.geometry.attributes.position.needsUpdate = true;
        particleSystem.rotation.y += 0.0003;
        particleSystem.rotation.x += 0.0001;
    }

    function cleanup() {
        if (particleSystem) {
            particleSystem.geometry.dispose();
            particleSystem.material.dispose();
            scene.remove(particleSystem);
        }
    }

    function setMobileMode() {
        if (particleSystem) {
            particleSystem.geometry.setDrawRange(0, Math.min(300, particleCount));
        }
    }

    return {
        createParticles,
        updateParticles,
        cleanup,
        setMobileMode,
    };
}
