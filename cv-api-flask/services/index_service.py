import os
import re
import threading
import pickle
import numpy as np

try:
    import faiss
except Exception:
    faiss = None

from utils.cv_ops import l2_normalize

CLASS_RE = re.compile(r"class_(\d+)\.index$")

class FaissIndexService:
    """
    Index FAISS par class_id.
    Similarité cosine via IndexFlatIP (inner product sur vecteurs L2-norm).
    Persistance: .index + .pkl (metadata alignée).
    Auto-load: scan le dossier au démarrage.
    """

    def __init__(self, base_dir: str, preload: bool = True):
        self.base_dir = base_dir
        os.makedirs(self.base_dir, exist_ok=True)

        self._lock = threading.Lock()
        self.indices = {}   # class_id -> faiss.Index
        self.meta = {}      # class_id -> list[dict]
        self.dim = None     # dimension globale (assumée identique)

        if preload:
            self.preload_existing()

    def _paths(self, class_id: int):
        idx_path = os.path.join(self.base_dir, f"class_{class_id}.index")
        meta_path = os.path.join(self.base_dir, f"class_{class_id}.pkl")
        return idx_path, meta_path

    def preload_existing(self):
        """Charge tous les index existants trouvés dans base_dir."""
        if faiss is None:
            return  # FAISS non dispo: on ne crash pas, juste pas d'indexation

        with self._lock:
            for fname in os.listdir(self.base_dir):
                m = CLASS_RE.match(fname)
                if not m:
                    continue
                class_id = int(m.group(1))
                idx_path, meta_path = self._paths(class_id)
                if not os.path.exists(meta_path):
                    continue

                index = faiss.read_index(idx_path)
                with open(meta_path, "rb") as f:
                    meta = pickle.load(f)

                self.indices[class_id] = index
                self.meta[class_id] = meta
                self.dim = index.d if self.dim is None else self.dim

    def ensure_class(self, class_id: int, dim: int):
        """Assure que l’index pour class_id existe et a la bonne dimension."""
        if faiss is None:
            raise RuntimeError("FAISS not available. Install faiss-cpu or use Python 3.12.")

        if class_id not in self.indices:
            idx_path, meta_path = self._paths(class_id)
            if os.path.exists(idx_path) and os.path.exists(meta_path):
                index = faiss.read_index(idx_path)
                with open(meta_path, "rb") as f:
                    meta = pickle.load(f)
                self.indices[class_id] = index
                self.meta[class_id] = meta
                self.dim = index.d if self.dim is None else self.dim
            else:
                self.indices[class_id] = faiss.IndexFlatIP(dim)
                self.meta[class_id] = []
                self.dim = dim if self.dim is None else self.dim

        # check dim
        if self.indices[class_id].d != dim:
            raise ValueError(f"FAISS dim mismatch for class {class_id}: {self.indices[class_id].d} != {dim}")

    def add_batch(self, class_id: int, vectors: np.ndarray, metadata_list: list[dict], persist: bool = True):
        """
        Ajoute N vecteurs (N,D) en une fois (plus rapide).
        """
        if faiss is None:
            raise RuntimeError("FAISS not available.")

        vectors = vectors.astype(np.float32)
        # L2 normalize rows (cosine)
        for i in range(vectors.shape[0]):
            vectors[i] = l2_normalize(vectors[i]).astype(np.float32)

        dim = int(vectors.shape[1])

        with self._lock:
            self.ensure_class(class_id, dim)
            self.indices[class_id].add(vectors)
            self.meta[class_id].extend(metadata_list)
            if persist:
                self._persist_locked(class_id)

    def search(self, class_id: int, query_vec: np.ndarray, top_k: int = 10):
        if faiss is None:
            raise RuntimeError("FAISS not available.")

        q = query_vec.astype(np.float32)
        q = l2_normalize(q).astype(np.float32).reshape(1, -1)

        with self._lock:
            if class_id not in self.indices or self.indices[class_id].ntotal == 0:
                return []
            index = self.indices[class_id]
            meta = self.meta.get(class_id, [])

            D, I = index.search(q, top_k)

        results = []
        for score, idx in zip(D[0].tolist(), I[0].tolist()):
            if idx == -1:
                continue
            m = meta[idx] if idx < len(meta) else {}
            results.append({"score": float(score), **m})
        return results

    def _persist_locked(self, class_id: int):
        idx_path, meta_path = self._paths(class_id)
        faiss.write_index(self.indices[class_id], idx_path)
        with open(meta_path, "wb") as f:
            pickle.dump(self.meta[class_id], f)
