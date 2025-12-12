import os
import uuid
from PIL import Image
from werkzeug.datastructures import FileStorage

def allowed_file(filename: str, allowed_ext: set[str]) -> bool:
    if "." not in filename:
        return False
    ext = filename.rsplit(".", 1)[1].lower()
    return ext in allowed_ext

def save_upload(file: FileStorage, upload_dir: str) -> str:
    os.makedirs(upload_dir, exist_ok=True)
    ext = file.filename.rsplit(".", 1)[1].lower()
    new_name = f"{uuid.uuid4().hex}.{ext}"
    path = os.path.join(upload_dir, new_name)
    file.save(path)
    return path

def get_image_size(path: str) -> tuple[int, int]:
    with Image.open(path) as img:
        return img.size  # (width, height)
