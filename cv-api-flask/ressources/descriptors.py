import cv2
from flask_restful import Resource
from flask import request, current_app

from utils.responses import ok, err
from utils.image_io import allowed_file, save_upload
from utils.cv_ops import crop_xyxy
from services.feature_service import FeatureService

class DescribeResource(Resource):
    def __init__(self):
        self.feat = FeatureService()

    def post(self):
        """
        POST /describe
        form-data:
          - image: File
          - x1,y1,x2,y2: float/int (bbox_xyxy)
        """
        if "image" not in request.files:
            return err("Missing file field 'image'", 400)

        file = request.files["image"]
        if file.filename == "":
            return err("Empty filename", 400)

        cfg = current_app.config
        if not allowed_file(file.filename, cfg["ALLOWED_EXTENSIONS"]):
            return err("Unsupported file type", 415, {"allowed": sorted(list(cfg["ALLOWED_EXTENSIONS"]))})

                # bbox params (OPTIONAL)
        x1s = request.form.get("x1")
        y1s = request.form.get("y1")
        x2s = request.form.get("x2")
        y2s = request.form.get("y2")

        has_any_bbox = any(v is not None and str(v).strip() != "" for v in (x1s, y1s, x2s, y2s))
        has_all_bbox = all(v is not None and str(v).strip() != "" for v in (x1s, y1s, x2s, y2s))

        if has_any_bbox and not has_all_bbox:
            return err("Partial bbox provided. Send x1,y1,x2,y2 all together.", 400)

        bbox = None
        if has_all_bbox:
            try:
                x1 = float(x1s); y1 = float(y1s); x2 = float(x2s); y2 = float(y2s)
                bbox = [x1, y1, x2, y2]
            except Exception:
                return err("Missing/invalid bbox fields x1,y1,x2,y2", 400)

        # save image then read with cv2
        img_path = save_upload(file, cfg["UPLOAD_DIR"])
        img = cv2.imread(img_path)
        if img is None:
            return err("Failed to read image", 400)

        # crop only if bbox exists, else use full image
        if bbox is not None:
            crop, bbox_fixed = crop_xyxy(img, bbox)
        else:
            crop = img
            h, w = img.shape[:2]
            bbox_fixed = [0.0, 0.0, float(w), float(h)]

        try:
            desc = self.feat.describe_object(crop)
        except Exception as e:
            return err("Descriptor extraction failed", 500, {"error": str(e)})

        return ok({
            "bbox_xyxy": list(map(float, bbox_fixed)),
            "descriptors": desc
        })
