# services/shape3d_normalizer.py

import numpy as np


class Shape3DNormalizer:
    """
    Normalisation géométrique 3D :
    - translation
    - mise à l'échelle
    - orientation (PCA)
    """

    @staticmethod
    def normalize(points: np.ndarray) -> np.ndarray:
        """
        Normalise un nuage de points 3D.

        Args:
            points (np.ndarray): (N,3)

        Returns:
            np.ndarray: (N,3) normalisé
        """
        assert points.ndim == 2 and points.shape[1] == 3

        # --- 1. Translation : centrer ---
        centroid = points.mean(axis=0)
        pts = points - centroid

        # --- 2. Mise à l’échelle : sphère unité ---
        max_dist = np.linalg.norm(pts, axis=1).max()
        if max_dist > 0:
            pts = pts / max_dist

        # --- 3. Orientation : PCA ---
        # covariance
        cov = np.cov(pts.T)

        # valeurs/vecteurs propres
        eigvals, eigvecs = np.linalg.eigh(cov)

        # trier par variance décroissante
        order = np.argsort(eigvals)[::-1]
        eigvecs = eigvecs[:, order]

        # alignement
        pts = pts @ eigvecs

        return pts.astype(np.float32)
