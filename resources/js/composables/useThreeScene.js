import { ref, onUnmounted } from 'vue';
import * as THREE from 'three';

export function useThreeScene(canvasRef) {
    const sceneReady = ref(false);
    let scene, camera, renderer;
    let animationId = null;
    let mounted = true;
    const shapes = [];
    const mouse = { x: 0, y: 0 };

    function initScene() {
        if (!canvasRef.value || !mounted) return;

        const width = window.innerWidth;
        const height = window.innerHeight;

        scene = new THREE.Scene();
        scene.fog = new THREE.FogExp2(0x0A0A0F, 0.0008);

        camera = new THREE.PerspectiveCamera(75, width / height, 0.1, 1000);
        camera.position.z = 28;

        renderer = new THREE.WebGLRenderer({
            alpha: true,
            antialias: true,
            powerPreference: 'high-performance',
        });
        renderer.setSize(width, height);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        canvasRef.value.appendChild(renderer.domElement);

        // Lights
        const ambient = new THREE.AmbientLight(0x404060, 0.5);
        scene.add(ambient);

        const light1 = new THREE.PointLight(0x7C3AED, 3, 60);
        light1.position.set(15, 15, 15);
        scene.add(light1);

        const light2 = new THREE.PointLight(0x2DD4BF, 2, 60);
        light2.position.set(-15, -15, 15);
        scene.add(light2);

        sceneReady.value = true;
    }

    function addShape(mesh) {
        if (scene) scene.add(mesh);
        shapes.push(mesh);
    }

    function animate(callback) {
        if (!mounted) return;
        animationId = requestAnimationFrame(() => animate(callback));

        const targetX = mouse.x / window.innerWidth * 2 - 1;
        const targetY = -(mouse.y / window.innerHeight) * 2 + 1;

        shapes.forEach(mesh => {
            if (mesh.userData.rotSpeed) {
                mesh.rotation.x += mesh.userData.rotSpeed.x;
                mesh.rotation.y += mesh.userData.rotSpeed.y;
                mesh.rotation.z += mesh.userData.rotSpeed.z;
            }
        });

        camera.position.x += (targetX * 1.5 - camera.position.x) * 0.05;
        camera.position.y += (targetY * 1 - camera.position.y) * 0.05;
        camera.lookAt(scene.position);

        if (callback) callback({
            mouseX: targetX,
            mouseY: targetY,
            time: Date.now() * 0.0005,
        });

        renderer.render(scene, camera);
    }

    function handleResize() {
        if (!camera || !renderer) return;
        const width = window.innerWidth;
        const height = window.innerHeight;
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        renderer.setSize(width, height);
    }

    function handleVisibility() {
        if (document.hidden && animationId) {
            cancelAnimationFrame(animationId);
            animationId = null;
        } else if (!document.hidden && !animationId && mounted) {
            animate(() => {});
        }
    }

    function cleanup() {
        mounted = false;
        if (animationId) {
            cancelAnimationFrame(animationId);
            animationId = null;
        }
        if (scene) {
            scene.traverse((obj) => {
                if (obj.geometry) obj.geometry.dispose();
                if (obj.material) {
                    if (Array.isArray(obj.material)) {
                        obj.material.forEach(m => m.dispose());
                    } else {
                        obj.material.dispose();
                    }
                }
            });
        }
        if (renderer) {
            renderer.dispose();
            if (canvasRef.value) {
                canvasRef.value.innerHTML = '';
            }
        }
        window.removeEventListener('resize', handleResize);
        document.removeEventListener('visibilitychange', handleVisibility);
    }

    function setMouse(x, y) {
        mouse.x = x;
        mouse.y = y;
    }

    window.addEventListener('resize', handleResize);
    document.addEventListener('visibilitychange', handleVisibility);

    return {
        sceneReady,
        scene,
        camera,
        renderer,
        initScene,
        addShape,
        animate,
        cleanup,
        setMouse,
        shapes,
    };
}
