import numpy as np
import cv2

def clamp_bbox_xyxy(bbox, w, h):
    x1, y1, x2, y2 = bbox
    x1 = max(0, min(int(round(x1)), w - 1))
    y1 = max(0, min(int(round(y1)), h - 1))
    x2 = max(0, min(int(round(x2)), w - 1))
    y2 = max(0, min(int(round(y2)), h - 1))
    if x2 <= x1: x2 = min(w - 1, x1 + 1)
    if y2 <= y1: y2 = min(h - 1, y1 + 1)
    return x1, y1, x2, y2

def crop_xyxy(img_bgr, bbox_xyxy):
    h, w = img_bgr.shape[:2]
    x1, y1, x2, y2 = clamp_bbox_xyxy(bbox_xyxy, w, h)
    return img_bgr[y1:y2, x1:x2].copy(), (x1, y1, x2, y2)

def l2_normalize(vec: np.ndarray, eps=1e-12) -> np.ndarray:
    norm = float(np.linalg.norm(vec))
    if norm < eps:
        return vec
    return vec / norm
