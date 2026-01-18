# test_index_3d.py

import os
from services.shape3d_index_service import Shape3DIndexService

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

MODELS_DIR = os.path.join(
    BASE_DIR,
    "..",
    "3dDataset",
    "All Models"
)

LABELS_CSV = os.path.join(BASE_DIR, "labels.csv")

svc = Shape3DIndexService()

svc.build_index(
    models_dir=MODELS_DIR,
    labels_csv=LABELS_CSV
)
