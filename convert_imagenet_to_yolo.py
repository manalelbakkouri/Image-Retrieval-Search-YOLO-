import os
import xml.etree.ElementTree as ET
from pathlib import Path
import shutil

# ==== À ADAPTER : chemin ABSOLU vers ton dossier DATA ====
# Exemple WSL : DATA_ROOT = Path("/home/manal/PROJECT/DATA")
# Exemple Windows Python : DATA_ROOT = Path(r"C:\Users\...\PROJECT\DATA")
DATA_ROOT = Path(r"C:\Users\admin\Documents\Master\S3\ComVision\mini projet 1\DATA")

IMAGES_RAW   = DATA_ROOT / "images_raw"
ANNOTS_TRAIN = DATA_ROOT / "annots_raw" / "train"
ANNOTS_VAL   = DATA_ROOT / "annots_raw" / "val"
YOLO_ROOT    = DATA_ROOT / "dataset_yolo"

# mapping synset (wnid) -> id de classe YOLO
classes = {
    "n02084071": 0,  # dog
    "n02121808": 1,  # cat
    "n02374451": 2,  # horse
    "n02411705": 3,  # sheep
    "n03001627": 4,  # chair
    "n02876657": 5,  # bottle
    "n04507155": 6,  # umbrella
    "n02958343": 7,  # car
    "n03790512": 8,  # motorcycle
    "n02691156": 9,  # airplane
    "n02834778": 10, # bicycle
    "n02924116": 11  # boat
}

def ensure_split_dirs(split: str):
    (YOLO_ROOT / "images" / split).mkdir(parents=True, exist_ok=True)
    (YOLO_ROOT / "labels" / split).mkdir(parents=True, exist_ok=True)

def find_image(filename: str) -> Path | None:
    """
    Cherche dans images_raw/* un fichier dont le nom est 'filename'.
    Retourne le chemin complet ou None.
    """
    for wnid_dir in IMAGES_RAW.iterdir():
        if not wnid_dir.is_dir():
            continue
        candidate = wnid_dir / filename
        if candidate.exists():
            return candidate
    return None

def convert_split(annots_dir: Path, split: str):
    ensure_split_dirs(split)
    out_img_dir = YOLO_ROOT / "images" / split
    out_lbl_dir = YOLO_ROOT / "labels" / split

    xml_files = list(annots_dir.rglob("*.xml"))
    print(f"[{split}] {len(xml_files)} fichiers XML trouvés")

    kept = 0

    for xml_file in xml_files:
        tree = ET.parse(xml_file)
        root = tree.getroot()

        size = root.find("size")
        if size is None:
            continue
        w = int(size.find("width").text)
        h = int(size.find("height").text)

        filename = root.find("filename").text  # ex: ILSVRC2012_train_00000001.JPEG

        yolo_lines = []

        # Parcours tous les objets annotés dans ce XML
        for obj in root.findall("object"):
            wnid = obj.find("name").text  # synset, ex: n02084071
            if wnid not in classes:
                continue  # on ignore les classes qui ne font pas partie des 12

            class_id = classes[wnid]

            bbox = obj.find("bndbox")
            xmin = int(bbox.find("xmin").text)
            ymin = int(bbox.find("ymin").text)
            xmax = int(bbox.find("xmax").text)
            ymax = int(bbox.find("ymax").text)

            x_center = (xmin + xmax) / 2 / w
            y_center = (ymin + ymax) / 2 / h
            bw = (xmax - xmin) / w
            bh = (ymax - ymin) / h

            yolo_lines.append(f"{class_id} {x_center} {y_center} {bw} {bh}")

        if not yolo_lines:
            # aucune bbox de nos classes dans ce XML
            continue

        # On cherche l'image correspondante dans images_raw/*
        img_path = find_image(filename)
        if img_path is None:
            # l'image ne fait pas partie des 15 synsets téléchargés
            continue

        # Copier l'image vers dataset_yolo/images/split
        out_img_path = out_img_dir / img_path.name
        if not out_img_path.exists():
            shutil.copy2(img_path, out_img_path)

        # Écrire le label YOLO
        out_lbl_path = out_lbl_dir / (img_path.stem + ".txt")
        with open(out_lbl_path, "w") as f:
            f.write("\n".join(yolo_lines))

        kept += 1

    print(f"[{split}] {kept} images retenues avec au moins une bbox de nos classes.")

if __name__ == "__main__":
    print("Conversion TRAIN...")
    convert_split(ANNOTS_TRAIN, "train")
    print("Conversion VAL...")
    convert_split(ANNOTS_VAL, "val")
    print("Terminé.")
