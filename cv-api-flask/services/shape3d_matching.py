# services/shape3d_matching.py
import numpy as np


def chi2_distance(h1, h2):
    return 0.5 * np.sum(
        ((h1 - h2) ** 2) / (h1 + h2 + 1e-8)
    )


def shape_context_distance(descA, descB):
    """
    Local features matching
    """
    distances = []

    for i in range(len(descA)):
        dmin = np.inf
        for j in range(len(descB)):
            d = chi2_distance(descA[i], descB[j])
            dmin = min(dmin, d)
        distances.append(dmin)

    return float(np.mean(distances))


def aggregate_descriptor(local_desc):
    """
    Global descriptor (for FAISS)
    """
    return np.mean(local_desc, axis=0)
