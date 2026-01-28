import numpy as np
from flask_restful import Resource
from flask import request, current_app

from utils.responses import ok, err
from services.index_service import FaissIndexService
from services.shape3d_index_service import Shape3DIndexService
from utils.responses import ok, err


import os
import tempfile

from services.shape3d_index_service import Shape3DIndexService
from services.shape3d_loader import Shape3DLoader
from services.shape3d_normalizer import Shape3DNormalizer
from services.shape_context_3d import ShapeContext3D
from services.shape3d_matching import aggregate_descriptor
from utils.responses import ok, err

class IndexAddResource(Resource):
    """
    POST /index/add
    JSON:
      {
        "class_id": 0,
        "items": [
          {"image_id": 12, "detection_id": 55, "vector": [...]} ,
          ...
        ]
      }
    """
    def __init__(self, index_service: FaissIndexService):
        self.index = index_service

    def post(self):
        data = request.get_json(silent=True)
        if not data:
            return err("Missing JSON body", 400)

        try:
            class_id = int(data["class_id"])
            items = data["items"]
        except Exception:
            return err("Invalid JSON. Need class_id and items[]", 400)

        if not isinstance(items, list) or len(items) == 0:
            return err("items must be a non-empty list", 400)

        vectors = []
        metas = []
        for it in items:
            if "vector" not in it:
                return err("Each item must include 'vector'", 400)
            vec = np.array(it["vector"], dtype=np.float32)
            meta = {k: it[k] for k in it.keys() if k != "vector"}
            vectors.append(vec)
            metas.append(meta)

        V = np.stack(vectors, axis=0).astype(np.float32)

        try:
           
            self.index.add_batch(class_id=class_id, vectors=V, metadata_list=metas, persist=True)
        except Exception as e:
            return err("Index add failed", 500, {"error": str(e)})

        return ok({"added": len(items), "class_id": class_id})



class SearchSimilarResource(Resource):
    """
    POST /search-similar
    JSON:
      {
        "class_id": 0,
        "vector": [...],
        "top_k": 10
      }
    """
    def __init__(self, index_service: FaissIndexService):
        self.index = index_service

    def post(self):
        data = request.get_json(silent=True)
        if not data:
            return err("Missing JSON body", 400)

        try:
            class_id = int(data["class_id"])
            vec = np.array(data["vector"], dtype=np.float32)
            top_k = int(data.get("top_k", 10))
        except Exception:
            return err("Invalid JSON: need class_id, vector, optional top_k", 400)

        try:
            results = self.index.search(class_id=class_id, query_vec=vec, top_k=top_k)
        except Exception as e:
            return err("FAISS search failed", 500, {"error": str(e)})

        return ok({"class_id": class_id, "top_k": top_k, "results": results})


class Index3DResource(Resource):

    def post(self):
        try:
            models_dir = request.json.get("models_dir")
            labels_csv = request.json.get("labels_csv")

            if not models_dir or not labels_csv:
                return err("models_dir and labels_csv required", 400)

            svc = Shape3DIndexService()
            svc.build_index(models_dir, labels_csv)

            return ok({"message": "Index 3D créé avec succès"})

        except Exception as e:
            return err("Indexation 3D échouée", 500, {"error": str(e)})






class Search3DResource(Resource):

    def post(self):
        try:
            if "file" not in request.files:
                return err("Fichier .obj manquant", 400)

            file = request.files["file"]
            top_k = int(request.form.get("top_k", 10))

            # Sauvegarde temporaire
            tmp = tempfile.NamedTemporaryFile(delete=False, suffix=".obj")
            file.save(tmp.name)

            # Pipeline 3D
            pts = Shape3DLoader.load_obj(tmp.name)
            pts = Shape3DNormalizer.normalize(pts)

            sc = ShapeContext3D()
            ref_idx = np.random.choice(
                pts.shape[0],
                min(50, pts.shape[0]),
                replace=False
            )

            local_desc = np.array([
                sc.compute(pts, i) for i in ref_idx
            ])

            query_desc = aggregate_descriptor(local_desc)

            # Recherche
            svc = Shape3DIndexService()
            results = svc.search(query_desc, top_k=top_k)

            os.unlink(tmp.name)

            return ok({
                "query_size": pts.shape[0],
                "results": results
            })

        except Exception as e:
            return err("Recherche 3D échouée", 500, {"error": str(e)})



class Stats3DResource(Resource):

    def post(self):
        try:
            query_label = request.json.get("query_label")
            results = request.json.get("results")

            if not query_label or not results:
                return err("query_label and results required", 400)

            correct = sum(
                1 for r in results if r["label"] == query_label
            )

            precision = correct / len(results)

            return ok({
                "precision": round(precision, 3),
                "correct": correct,
                "total": len(results)
            })

        except Exception as e:
            return err("Statistiques échouées", 500, {"error": str(e)})
