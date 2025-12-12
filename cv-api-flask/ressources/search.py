import numpy as np
from flask_restful import Resource
from flask import request, current_app

from utils.responses import ok, err
from services.index_service import FaissIndexService

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
