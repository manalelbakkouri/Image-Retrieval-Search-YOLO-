from ultralytics import YOLO
from pathlib import Path
import shutil
import random

# ----- CONFIG -----
DATA_ROOT = Path(r"C:\Users\admin\Documents\Master\S3\ComVision\mini projet 1\DATA")
IMAGES_RAW = DATA_ROOT / "images_raw"
YOLO_ROOT  = DATA_ROOT / "dataset_yolo"

# pour que le split train/val soit reproductible
random.seed(42)

project_classes = [
    "dog",        # 0
    "cat",        # 1
    "horse",      # 2
    "sheep",      # 3
    "bird",       # 4
    "chair",      # 5
    "bottle",     # 6
    "ball",       # 7  (sports ball)
    "umbrella",   # 8
    "fork",       # 9
    "car",        # 10
    "motorcycle", # 11
    "airplane",   # 12
    "bicycle",    # 13
    "boat"        # 14
]

# COCO class index -> index dans project_classes
coco_to_proj = {
    16: 0,  # dog
    15: 1,  # cat
    17: 2,  # horse
    18: 3,  # sheep
    14: 4,  # bird
    56: 5,  # chair
    39: 6,  # bottle
    32: 7,  # sports ball -> ball
    25: 8,  # umbrella
    42: 9,  # fork
    2:  10, # car
    3:  11, # motorcycle
    4:  12, # airplane
    1:  13, # bicycle
    8:  14  # boat
}

# hyperparamètres
CONF_THRESH = 0.4      # seuil de confiance
TRAIN_RATIO = 0.8      # 80% train / 20% val
MAX_IMAGES_PER_CLASS = None  # ou par ex. 300 pour limiter

# Charger YOLOv8n pré-entraîné COCO
model = YOLO("yolov8n.pt")

def ensure_dirs():
    for split in ["train", "val"]:
        (YOLO_ROOT / "images" / split).mkdir(parents=True, exist_ok=True)
        (YOLO_ROOT / "labels" / split).mkdir(parents=True, exist_ok=True)

def auto_annotate():
    ensure_dirs()

    # compteur d'images par classe (optionnel si tu veux équilibrer)
    per_class_count = {cls: 0 for cls in project_classes}

    wnid_dirs = [d for d in IMAGES_RAW.iterdir() if d.is_dir()]
    print(f"{len(wnid_dirs)} dossiers synsets trouvés dans images_raw.")

    for wnid_dir in wnid_dirs:
        print(f"Traitement du synset {wnid_dir.name} ...")
        image_files = [p for p in wnid_dir.iterdir() if p.suffix.lower() in [".jpg", ".jpeg", ".png", ".jpeg", ".bmp", ".tiff", ".gif", ".JPEG"]]

        for img_path in image_files:
            # Détection avec YOLO
            results = model(str(img_path), conf=CONF_THRESH, verbose=False)[0]

            lines = []
            used_project_classes = set()

            for box in results.boxes:
                coco_id = int(box.cls[0])
                if coco_id not in coco_to_proj:
                    continue

                proj_id = coco_to_proj[coco_id]

                # récupérer xywh normalisé directement
                x_center, y_center, w, h = box.xywhn[0].tolist()
                lines.append(f"{proj_id} {x_center} {y_center} {w} {h}")
                used_project_classes.add(project_classes[proj_id])

            if not lines:
                # aucune bbox des 15 classes -> on ignore cette image
                continue

            # Option : limiter le nombre d'images par classe (en se basant sur la 1ère classe vue)
            main_class = next(iter(used_project_classes))
            if MAX_IMAGES_PER_CLASS is not None:
                if per_class_count[main_class] >= MAX_IMAGES_PER_CLASS:
                    continue

            # split train/val aléatoire
            split = "train" if random.random() < TRAIN_RATIO else "val"

            out_img_dir = YOLO_ROOT / "images" / split
            out_lbl_dir = YOLO_ROOT / "labels" / split

            out_img_path = out_img_dir / img_path.name
            out_lbl_path = out_lbl_dir / (img_path.stem + ".txt")

            # copier l'image
            if not out_img_path.exists():
                shutil.copy2(img_path, out_img_path)

            # écrire le label
            with open(out_lbl_path, "w") as f:
                f.write("\n".join(lines))

            per_class_count[main_class] += 1

    print("Annotation automatique terminée.")
    print("Nombre d'images par classe (approx) :")
    for cls, c in per_class_count.items():
        print(f"  {cls}: {c}")

if __name__ == "__main__":
    auto_annotate()
