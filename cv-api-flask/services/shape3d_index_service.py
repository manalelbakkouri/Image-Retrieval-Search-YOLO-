# services/shape3d_index_service.py
import os
import pickle
import numpy as np
import faiss

from services.shape_context_3d import ShapeContext3D
from services.shape3d_loader import Shape3DLoader
from services.shape3d_normalizer import Shape3DNormalizer
from services.shape3d_matching import aggregate_descriptor


class Shape3DIndexService:
    """
    FAISS Index for 3D CBIR
    """

    def __init__(self, index_dir="data/faiss/shape3d"):
        self.index_dir = index_dir
        os.makedirs(index_dir, exist_ok=True)

        self.index_path = os.path.join(index_dir, "shape3d.index")
        self.meta_path = os.path.join(index_dir, "metadata.pkl")

        self.index = None
        self.metadata = []

    # --------------------------------------------------
    def build_index(self, models_dir, labels_csv):
        import pandas as pd

        labels_df = pd.read_csv(labels_csv)
        label_map = dict(zip(labels_df["filename"], labels_df["label"]))

        sc = ShapeContext3D()
        descriptors = []
        self.metadata = []

        files = sorted([
            f for f in os.listdir(models_dir)
            if f.lower().endswith(".obj")
        ])

        print(f"[INFO] Indexation de {len(files)} modèles 3D")

        for idx, fname in enumerate(files):
            path = os.path.join(models_dir, fname)

            # 1. Load
            pts = Shape3DLoader.load_obj(path)

            # 2. Normalize
            pts = Shape3DNormalizer.normalize(pts)

            # 3. Local descriptors
            num_keypoints = min(50, pts.shape[0])
            ref_indices = np.random.choice(
                pts.shape[0],
                num_keypoints,
                replace=False
            )

            local_desc = np.array([
                sc.compute(pts, ref_idx)
                for ref_idx in ref_indices
            ])

            # 4. Global descriptor
            global_desc = aggregate_descriptor(local_desc)

            descriptors.append(global_desc.astype("float32"))

            self.metadata.append({
                "id": idx,
                "filename": fname,
                "label": label_map.get(fname, "Unknown"),
                "local_desc": local_desc
            })

        X = np.vstack(descriptors)
        dim = X.shape[1]

        self.index = faiss.IndexFlatL2(dim)
        self.index.add(X)

        faiss.write_index(self.index, self.index_path)
        with open(self.meta_path, "wb") as f:
            pickle.dump(self.metadata, f)

        print("[OK] Index FAISS 3D créé")

    # --------------------------------------------------
    def load(self):
        self.index = faiss.read_index(self.index_path)
        with open(self.meta_path, "rb") as f:
            self.metadata = pickle.load(f)

    # --------------------------------------------------
    def search(self, query_desc, top_k=10):
        if self.index is None:
            self.load()

        q = query_desc.astype("float32").reshape(1, -1)
        distances, indices = self.index.search(q, top_k)

        results = []
        for i, d in zip(indices[0], distances[0]):
            meta = self.metadata[i]
            results.append({
                "filename": meta["filename"],
                "label": meta["label"],
                "distance": float(d)
            })

        return results
    def add_one(self, obj_path, label="Unknown"):
        """
        Ajoute dynamiquement un modèle 3D à l'index FAISS
        """

        # Charger ou créer l'index
        if os.path.exists(self.index_path):
            self.load()
        else:
            self.index = None
            self.metadata = []

        # Pipeline 3D
        pts = Shape3DLoader.load_obj(obj_path)
        pts = Shape3DNormalizer.normalize(pts)

        sc = ShapeContext3D()

        num_keypoints = min(50, pts.shape[0])
        ref_indices = np.random.choice(
            pts.shape[0],
            num_keypoints,
            replace=False
        )

        local_desc = np.array([
            sc.compute(pts, idx)
            for idx in ref_indices
        ])

        global_desc = aggregate_descriptor(local_desc).astype("float32")

        # Création index si vide
        if self.index is None:
            dim = global_desc.shape[0]
            self.index = faiss.IndexFlatL2(dim)

        # Ajout FAISS
        self.index.add(global_desc.reshape(1, -1))

        model_id = len(self.metadata)

        self.metadata.append({
            "id": model_id,
            "filename": os.path.basename(obj_path),
            "label": label,
        })

        # Sauvegarde
        faiss.write_index(self.index, self.index_path)
        with open(self.meta_path, "wb") as f:
            pickle.dump(self.metadata, f)

        return {
            "id": model_id,
            "filename": os.path.basename(obj_path),
            "label": label
        }

