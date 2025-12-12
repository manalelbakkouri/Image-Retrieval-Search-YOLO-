import os

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

class Config:
    # Paths
    WEIGHTS_PATH = os.getenv("WEIGHTS_PATH", os.path.join(BASE_DIR, "weights", "best.pt"))
    UPLOAD_DIR = os.getenv("UPLOAD_DIR", os.path.join(BASE_DIR, "data", "uploads"))
    TMP_DIR = os.getenv("TMP_DIR", os.path.join(BASE_DIR, "data", "tmp"))

    # Server
    HOST = os.getenv("FLASK_HOST", "127.0.0.1")
    PORT = int(os.getenv("FLASK_PORT", "5000"))
    DEBUG = os.getenv("FLASK_DEBUG", "1") == "1"

    # Upload limits (10MB default)
    MAX_CONTENT_LENGTH = int(os.getenv("MAX_CONTENT_LENGTH", str(10 * 1024 * 1024)))

    # YOLO settings
    YOLO_CONF = float(os.getenv("YOLO_CONF", "0.25"))
    YOLO_IOU = float(os.getenv("YOLO_IOU", "0.45"))
    YOLO_IMG_SIZE = int(os.getenv("YOLO_IMG_SIZE", "640"))

    # Allowed extensions
    ALLOWED_EXTENSIONS = {"jpg", "jpeg", "png", "webp"}
