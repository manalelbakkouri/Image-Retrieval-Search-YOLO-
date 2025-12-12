from flask_restful import Resource
from flask import request, current_app
from utils.responses import ok, err
from utils.image_io import allowed_file, save_upload, get_image_size

class DetectResource(Resource):
    def __init__(self, yolo_service):
        self.yolo = yolo_service

    def post(self):
        if "image" not in request.files:
            return err("Missing file field 'image'", 400)

        file = request.files["image"]
        if file.filename == "":
            return err("Empty filename", 400)

        cfg = current_app.config
        if not allowed_file(file.filename, cfg["ALLOWED_EXTENSIONS"]):
            return err("Unsupported file type", 415, {"allowed": sorted(list(cfg["ALLOWED_EXTENSIONS"]))})

        # Save upload
        img_path = save_upload(file, cfg["UPLOAD_DIR"])
        w, h = get_image_size(img_path)

        # YOLO inference
        try:
            results = self.yolo.predict(
                image_path=img_path,
                conf=cfg["YOLO_CONF"],
                iou=cfg["YOLO_IOU"],
                imgsz=cfg["YOLO_IMG_SIZE"]
            )
        except Exception as e:
            return err("YOLO inference failed", 500, {"error": str(e)})

        # Parse results
        detections = []
        r0 = results[0]
        names = r0.names  # dict: id -> name

        if r0.boxes is not None and len(r0.boxes) > 0:
            # xyxy: (N, 4), conf: (N,), cls: (N,)
            xyxy = r0.boxes.xyxy.cpu().numpy()
            confs = r0.boxes.conf.cpu().numpy()
            clss = r0.boxes.cls.cpu().numpy().astype(int)

            for bbox, conf, cls_id in zip(xyxy, confs, clss):
                x1, y1, x2, y2 = bbox.tolist()
                detections.append({
                    "class_id": int(cls_id),
                    "class_name": names.get(int(cls_id), str(cls_id)),
                    "confidence": float(conf),
                    "bbox_xyxy": [float(x1), float(y1), float(x2), float(y2)]
                })

        return ok({
            "image": {"width": w, "height": h},
            "model": "best.pt",
            "detections": detections,
            "count": len(detections)
        })
