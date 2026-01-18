# services/shape3d_local_descriptor.py

import numpy as np


class ShapeContext3DLocal:
    """
    Shape Context 3D - Descripteurs LOCAUX
    """

    def __init__(
        self,
        n_keypoints=150,
        r_bins=5,
        theta_bins=12,
        phi_bins=6
    ):
        self.n_keypoints = n_keypoints
        self.r_bins = r_bins
        self.theta_bins = theta_bins
        self.phi_bins = phi_bins

    def compute(self, points: np.ndarray) -> np.ndarray:
        """
        Args:
            points (np.ndarray): (N,3) normalisés

        Returns:
            np.ndarray: (K, D) descripteurs locaux
        """
        N = points.shape[0]

        # sélection de points clés
        idx = np.random.choice(N, min(self.n_keypoints, N), replace=False)
        keypoints = points[idx]

        local_descs = []

        for p in keypoints:
            rel = points - p

            r = np.linalg.norm(rel, axis=1) + 1e-6
            theta = np.arctan2(rel[:, 1], rel[:, 0])
            phi = np.arccos(rel[:, 2] / r)

            r_log = np.log(r)

            hist, _ = np.histogramdd(
                np.vstack([r_log, theta, phi]).T,
                bins=[self.r_bins, self.theta_bins, self.phi_bins],
                range=[
                    [r_log.min(), r_log.max()],
                    [-np.pi, np.pi],
                    [0, np.pi]
                ]
            )

            hist = hist.flatten().astype(np.float32)

            s = hist.sum()
            if s > 0:
                hist /= s

            local_descs.append(hist)

        return np.vstack(local_descs)  # (K, D)
