import numpy as np


class ShapeContext3D:
    """
    Implémentation du descripteur local 3D Shape Context
    (Körtgen et al., Local Features Based Similarity)
    """

    def __init__(
        self,
        radial_bins=5,
        theta_bins=6,
        phi_bins=6,
        r_min=0.01,
        r_max=2.0
    ):
        self.radial_bins = radial_bins
        self.theta_bins = theta_bins
        self.phi_bins = phi_bins
        self.r_min = r_min
        self.r_max = r_max

    def compute(self, points, ref_idx):
        """
        Calcule le Shape Context 3D pour un point de référence
        """
        ref = points[ref_idx]
        diff = points - ref
        dist = np.linalg.norm(diff, axis=1)

        mask = dist > 1e-6
        diff = diff[mask]
        dist = dist[mask]

        # Coordonnées sphériques
        r = dist
        theta = np.arccos(np.clip(diff[:, 2] / r, -1, 1))
        phi = np.mod(np.arctan2(diff[:, 1], diff[:, 0]), 2 * np.pi)

        # Bins
        r_bins = np.logspace(
            np.log10(self.r_min),
            np.log10(self.r_max),
            self.radial_bins + 1
        )
        theta_bins = np.linspace(0, np.pi, self.theta_bins + 1)
        phi_bins = np.linspace(0, 2 * np.pi, self.phi_bins + 1)

        hist = np.zeros(
            (self.radial_bins, self.theta_bins, self.phi_bins),
            dtype=np.float32
        )

        for i in range(len(r)):
            rb = np.searchsorted(r_bins, r[i]) - 1
            tb = np.searchsorted(theta_bins, theta[i]) - 1
            pb = np.searchsorted(phi_bins, phi[i]) - 1

            if 0 <= rb < self.radial_bins and \
               0 <= tb < self.theta_bins and \
               0 <= pb < self.phi_bins:
                hist[rb, tb, pb] += 1

        hist = hist.flatten()
        hist /= (hist.sum() + 1e-8)
        return hist


def compute_shape_contexts(points, n_samples=50):
    """
    EXTRACTION LOCALE MULTI-POINTS
    → Local features based similarity (article)
    """
    sc = ShapeContext3D()

    n = min(len(points), n_samples)
    indices = np.random.choice(len(points), n, replace=False)

    descriptors = []
    for idx in indices:
        descriptors.append(sc.compute(points, idx))

    return np.array(descriptors, dtype=np.float32)
