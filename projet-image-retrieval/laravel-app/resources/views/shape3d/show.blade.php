@extends('layouts.app')
@section('title','VisioSeek — Viewer 3D')

@section('content')
<style>
  #viewer {
    width: 100%;
    height: calc(100vh - 140px); /* quasi plein écran */
    background: #0f172a;
    border-radius: 16px;
    overflow: hidden;
  }
</style>

<div class="container-fluid">
  <h3 class="mb-3">Visualisation 3D</h3>

  <div class="card mb-4">
    <div class="card-body">
      <div id="viewer"></div>

      <div class="mt-2 text-muted">
        Fichier : <b>{{ $filename }}</b>
      </div>

      <form method="POST"
            action="{{ route('shape3d.search.from.model') }}"
            class="mt-3">
        @csrf
        <input type="hidden" name="filename" value="{{ $filename }}">
        <button class="btn btn-primary">
          Rechercher modèles similaires
        </button>
      </form>
    </div>
  </div>
</div>

<!-- ============================= -->
<!-- THREE.JS IMPORT MAP -->
<!-- ============================= -->
<script type="importmap">
{
  "imports": {
    "three": "https://cdn.jsdelivr.net/npm/three@0.150.1/build/three.module.js",
    "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.150.1/examples/jsm/"
  }
}
</script>

<!-- ============================= -->
<!-- THREE.JS VIEWER -->
<!-- ============================= -->
<script type="module">
import * as THREE from "three";
import { OrbitControls } from "three/addons/controls/OrbitControls.js";
import { OBJLoader } from "three/addons/loaders/OBJLoader.js";

const container = document.getElementById("viewer");

/* ============================= */
/* SCENE */
/* ============================= */
const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0f172a);

/* ============================= */
/* CAMERA */
/* ============================= */
const camera = new THREE.PerspectiveCamera(
  60,
  container.clientWidth / container.clientHeight,
  0.01,
  100
);
camera.position.set(0, 1.2, 4);

/* ============================= */
/* RENDERER */
/* ============================= */
const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setSize(container.clientWidth, container.clientHeight);
renderer.setPixelRatio(window.devicePixelRatio);
container.appendChild(renderer.domElement);

/* ============================= */
/* CONTROLS (STABLE & PRO) */
/* ============================= */
const controls = new OrbitControls(camera, renderer.domElement);
controls.enableRotate = true;
controls.rotateSpeed = 0.6;

controls.enableZoom = true;
controls.zoomSpeed = 0.8;
controls.minDistance = 1.2;
controls.maxDistance = 8;

controls.enablePan = false;
controls.minPolarAngle = Math.PI * 0.25;
controls.maxPolarAngle = Math.PI * 0.85;

controls.enableDamping = true;
controls.dampingFactor = 0.08;

/* ============================= */
/* LIGHTS */
/* ============================= */
scene.add(new THREE.AmbientLight(0xffffff, 0.6));

const keyLight = new THREE.DirectionalLight(0xffffff, 0.9);
keyLight.position.set(3, 6, 4);
scene.add(keyLight);

const fillLight = new THREE.DirectionalLight(0xffffff, 0.4);
fillLight.position.set(-3, 2, -4);
scene.add(fillLight);

/* ============================= */
/* GROUND */
/* ============================= */
const ground = new THREE.Mesh(
  new THREE.PlaneGeometry(1, 1),
  new THREE.MeshStandardMaterial({
    color: 0x334155,
    roughness: 0.9
  })
);
ground.rotation.x = -Math.PI / 2;
scene.add(ground);


/* ============================= */
/* LOAD OBJ */
/* ============================= */
const loader = new OBJLoader();
const objUrl = "{{ asset('storage/models3d/'.$filename) }}";

console.log("Chargement OBJ :", objUrl);

loader.load(
  objUrl,
  (object) => {

    // Matériau propre
    object.traverse((child) => {
      if (child.isMesh) {
        child.material = new THREE.MeshStandardMaterial({
          color: 0xd1d5db,
          roughness: 0.45,
          metalness: 0.1
        });
      }
    });

    // Bounding box
    const box = new THREE.Box3().setFromObject(object);
    const size = box.getSize(new THREE.Vector3());
    const center = box.getCenter(new THREE.Vector3());

    // Centrage
    object.position.sub(center);
    scene.add(object);

    // 📷 CAMÉRA ADAPTATIVE
    const maxDim = Math.max(size.x, size.y, size.z);
    const fov = camera.fov * (Math.PI / 180);
    let cameraZ = Math.abs(maxDim / Math.sin(fov / 2));

    cameraZ *= 1.1; // marge confortable

    camera.position.set(0, maxDim * 0.6, cameraZ);
    camera.lookAt(0, 0, 0);

    camera.near = cameraZ / 100;
    camera.far = cameraZ * 100;
    camera.updateProjectionMatrix();

    // 🎛️ CONTROLS STABLES
    controls.target.set(0, 0, 0);
    controls.minDistance = maxDim * 0.5;
    controls.maxDistance = maxDim * 6;
    controls.update();

    // 🟦 SOL À LA BONNE TAILLE
    ground.scale.set(maxDim * 2, maxDim * 2, 1);
    ground.position.y = -size.y / 2 - 0.02;
  },
  undefined,
  (err) => console.error("Erreur OBJ", err)
);


/* ============================= */
/* RESIZE */
/* ============================= */
window.addEventListener("resize", () => {
  camera.aspect = container.clientWidth / container.clientHeight;
  camera.updateProjectionMatrix();
  renderer.setSize(container.clientWidth, container.clientHeight);
});

/* ============================= */
/* ANIMATION LOOP */
/* ============================= */
function animate() {
  requestAnimationFrame(animate);
  controls.update();
  renderer.render(scene, camera);
}
animate();
</script>
@endsection
