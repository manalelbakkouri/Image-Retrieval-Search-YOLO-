# predict analogy using YOLO model with thread-safe lazy loading

from __future__ import annotations
from ultralytics import YOLO
import threading

class YoloService:
    """
    Loads YOLO model once and serves predictions.
    Thread-safe lazy loading.
    """
    _lock = threading.Lock()

    def __init__(self, weights_path: str):
        self.weights_path = weights_path
        self.model = None

    def load(self) -> None:
        if self.model is None:
            with self._lock:
                if self.model is None:
                    self.model = YOLO(self.weights_path)

    def predict(self, image_path: str, conf: float, iou: float, imgsz: int):
        self.load()
        results = self.model.predict(
            source=image_path,
            conf=conf,
            iou=iou,
            imgsz=imgsz,
            verbose=False
        )
        return results
