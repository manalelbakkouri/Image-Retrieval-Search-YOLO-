import os
import threading
import pickle
import numpy as np

try:
    import faiss
except Exception:
    faiss = None

from utils.cv_ops import l2_normalize

class FaissIndexService:
    """
    Index FAISS par classe_id (0..14). Similarité cosine via IndexFlatIP
    (Inner Product sur vecteurs L2-normalisés).
    """
    def __init__(self, base_dir: str):
        self.base_dir = base_dir
        os.makedirs(self.base_dir, exist_ok=True)
        self._lock = threading.Lock()

        self.indices = {}      # class_id -> faiss index
        self.meta = {}         # class_id -> list of metadata aligned with vectors
        self.dim = None

    def _paths(self, class_id: int):
        idx_path = os.path.join(self.base_dir, f"class_{class_id}.index")
        meta_path = os.path.join(self.base_dir, f"class_{class_id}.pkl")
        return idx_path, meta_path

    def load_class(self, class_id: int):
        if faiss is None:
            raise RuntimeError("FAISS not available. Install faiss-cpu or use Python 3.12.")

        idx_path, meta_path = self._paths(class_id)
        with self._lock:
            if class_id in self.indices:
                return

            if os.path.exists(idx_path) and os.path.exists(meta_path):
                index = faiss.read_index(idx_path)
                with open(meta_path, "rb") as f:
                    meta = pickle.load(f)
                self.indices[class_id] = index
                self.meta[class_id] = meta
                self.dim = index.d
            else:
                self.indices[class_id] = None
                self.meta[class_id] = []

    def add(self, class_id: int, vectors: np.ndarray, metadata_list: list[dict]):
        """
        vectors: (N, D) float32
        metadata_list: N dicts, ex: {"image_id":..., "detection_id":...}
        """
        if faiss is None:
            raise RuntimeError("FAISS not available.")

        vectors = vectors.astype(np.float32)
        # L2 normalize rows
        for i in range(vectors.shape[0]):
            vectors[i] = l2_normalize(vectors[i]).astype(np.float32)

        with self._lock:
            # init class index if needed
            if class_id not in self.indices:
                self.load_class(class_id)

            if self.indices[class_id] is None:
                d = vectors.shape[1]
                self.dim = d
                self.indices[class_id] = faiss.IndexFlatIP(d)

            # dimension check
            if vectors.shape[1] != self.indices[class_id].d:
                raise ValueError(f"Vector dim mismatch: got {vectors.shape[1]}, expected {self.indices[class_id].d}")

            self.indices[class_id].add(vectors)
            self.meta[class_id].extend(metadata_list)

            self._persist(class_id)

    def search(self, class_id: int, query_vec: np.ndarray, top_k: int = 10):
        if faiss is None:
            raise RuntimeError("FAISS not available.")

        self.load_class(class_id)
        with self._lock:
            index = self.indices.get(class_id)
            meta = self.meta.get(class_id, [])

            if index is None or index.ntotal == 0:
                return []

        q = query_vec.astype(np.float32)
        q = l2_normalize(q).astype(np.float32)
        q = q.reshape(1, -1)

        with self._lock:
            D, I = index.search(q, top_k)

        results = []
        for score, idx in zip(D[0].tolist(), I[0].tolist()):
            if idx == -1:
                continue
            m = meta[idx] if idx < len(meta) else {}
            results.append({
                "score": float(score),
                **m
            })
        return results

    def _persist(self, class_id: int):
        idx_path, meta_path = self._paths(class_id)
        faiss.write_index(self.indices[class_id], idx_path)
        with open(meta_path, "wb") as f:
            pickle.dump(self.meta[class_id], f)
