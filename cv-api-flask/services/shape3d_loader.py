# services/shape3d_loader.py

import os
import trimesh
import numpy as np


class Shape3DLoader:
    """
    Chargement robuste de modèles 3D (.obj)
    Sortie : nuage de points (N, 3)
    """

    @staticmethod
    def load_obj(path: str, sample_points: int = 2048) -> np.ndarray:

        if not os.path.isfile(path):
            raise FileNotFoundError(f"Fichier OBJ introuvable : {path}")

        # Load mesh or scene
        mesh_or_scene = trimesh.load(path, process=False)

        # Scene → concatenate meshes
        if isinstance(mesh_or_scene, trimesh.Scene):
            meshes = [
                g for g in mesh_or_scene.geometry.values()
                if isinstance(g, trimesh.Trimesh)
            ]
            if not meshes:
                raise ValueError(f"Aucun mesh valide dans la scène : {path}")
            mesh = trimesh.util.concatenate(meshes)
        else:
            mesh = mesh_or_scene

        if mesh.is_empty or mesh.vertices.shape[0] == 0:
            raise ValueError(f"Mesh vide ou invalide : {path}")

        #  Supported cleanup (CURRENT API)
        mesh.remove_unreferenced_vertices()
        mesh.merge_vertices()          # replaces duplicate vertices
        mesh.process(validate=True)    # handles degeneracies internally

        # Normalisation (important for CBIR)
        mesh.vertices -= mesh.centroid
        scale = np.linalg.norm(mesh.vertices, axis=1).max()
        if scale > 0:
            mesh.vertices /= scale

        # Sample surface points
        try:
            points = mesh.sample(sample_points)
        except Exception as e:
            raise RuntimeError(f"Échantillonnage échoué pour {path}: {e}")

        return points.astype(np.float32)
