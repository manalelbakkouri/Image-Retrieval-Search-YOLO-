@extends('layouts.app')
@section('title','Viewer 3D')

@section('content')
<style>
#viewer{width:100%;height:600px;background:#0b1220;border-radius:16px}
</style>

<h3 class="mb-3">{{ basename(request()->path()) }}</h3>

<div id="viewer"></div>

<script type="module">
import * as THREE from "https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js";
import { OrbitControls } from "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/controls/OrbitControls.js";
import { OBJLoader } from "https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/loaders/OBJLoader.js";

const container = document.getElementById("viewer");
const scene = new THREE.Scene();
scene.background = new THREE.Color(0x0b1220);

const camera = new THREE.PerspectiveCamera(45, container.clientWidth/container.clientHeight, 0.01, 1000);
camera.position.set(0, 0.8, 2.8);

const renderer = new THREE.WebGLRenderer({ antialias:true });
renderer.setSize(container.clientWidth, container.clientHeight);
container.appendChild(renderer.domElement);

const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;

scene.add(new THREE.AmbientLight(0xffffff, 0.8));
const light = new THREE.DirectionalLight(0xffffff, 1);
light.position.set(3, 3, 3);
scene.add(light);

const loader = new OBJLoader();
loader.load(
  window.location.href,
  obj => {
    obj.traverse(c => {
      if (c.isMesh) {
        c.material = new THREE.MeshStandardMaterial({
          color:0xbfc7d5,
          roughness:0.8,
          metalness:0.1
        });
      }
    });

    // centrage + normalisation
    const box = new THREE.Box3().setFromObject(obj);
    const size = new THREE.Vector3();
    box.getSize(size);
    const center = new THREE.Vector3();
    box.getCenter(center);

    obj.position.sub(center);

    const scale = 1.5 / Math.max(size.x, size.y, size.z);
    obj.scale.setScalar(scale);

    scene.add(obj);
  }
);

function animate(){
  requestAnimationFrame(animate);
  controls.update();
  renderer.render(scene, camera);
}
animate();
</script>
@endsection
