import os
import pandas as pd

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATASET_ROOT = os.path.join(BASE_DIR, "..", "3dDataset")

GROUNDTRUTH_XLS = os.path.join(
    DATASET_ROOT,
    "3D_Pottery_Groundtruth_and_Metadata.xls"
)

OUTPUT = os.path.join(BASE_DIR, "labels.csv")

# Read Excel
df = pd.read_excel(GROUNDTRUTH_XLS, engine="xlrd")

# Optional: clean column names (very important)
df.columns = df.columns.str.strip()

labels = []
for _, row in df.iterrows():
    filename = row["Filename"]          # e.g. 3DMillenium_bottle01.obj
    label = row["Shape Class"]           # e.g. Modern-Bottle
    labels.append((filename, label))

labels_df = pd.DataFrame(labels, columns=["filename", "label"])
labels_df.to_csv(OUTPUT, index=False)

print("labels.csv généré avec succès")
