import os
import tempfile
import numpy as np
from flask_restful import Resource
from flask import request, current_app

from utils.responses import ok, err

from services.shape3d_index_service import Shape3DIndexService
from services.shape3d_loader import Shape3DLoader
from services.shape3d_normalizer import Shape3DNormalizer
from services.shape_context_3d import ShapeContext3D
from services.shape3d_matching import aggregate_descriptor


# ==========================================================
# INDEXATION 3D (DYNAMIQUE - MODELS UPLOADÉS UNIQUEMENT)
# ==========================================================
class Index3DResource(Resource):
    """
    POST /index-3d/add
    Ajout dynamique d'un modèle 3D à FAISS
    """

    def post(self):
        try:
            if "file" not in request.files:
                return err("Fichier .obj manquant", 400)

            file = request.files["file"]
            label = request.form.get("label", "Unknown")

            tmp = tempfile.NamedTemporaryFile(
                delete=False,
                suffix=".obj",
                dir=current_app.config["TMP_DIR"]
            )
            file.save(tmp.name)

            svc = Shape3DIndexService()
            meta = svc.add_one(tmp.name, label)

            os.unlink(tmp.name)

            return ok({
                "message": "Modèle indexé avec succès",
                "model": meta
            })

        except Exception as e:
            return err("Indexation dynamique échouée", 500, {"error": str(e)})



# ==========================================================
# RECHERCHE PAR SIMILARITÉ 3D
# ==========================================================
class Search3DResource(Resource):

    def post(self):
        try:
            if "file" not in request.files:
                return err("Fichier .obj manquant", 400)

            file = request.files["file"]
            top_k = int(request.form.get("top_k", 10))

            # --------------------------------------------------
            # 1. Sauvegarde temporaire
            # --------------------------------------------------
            tmp = tempfile.NamedTemporaryFile(
                delete=False,
                suffix=".obj",
                dir=current_app.config["TMP_DIR"]
            )
            file.save(tmp.name)

            # --------------------------------------------------
            # 2. Pipeline 3D
            # --------------------------------------------------
            pts = Shape3DLoader.load_obj(tmp.name)
            pts = Shape3DNormalizer.normalize(pts)

            sc = ShapeContext3D()

            num_points = min(50, pts.shape[0])
            ref_idx = np.random.choice(
                pts.shape[0],
                num_points,
                replace=False
            )

            local_desc = np.array([
                sc.compute(pts, i) for i in ref_idx
            ])

            query_desc = aggregate_descriptor(local_desc)

            # --------------------------------------------------
            # 3. Index FAISS (AUTO-BUILD SI ABSENT)
            # --------------------------------------------------
            svc = Shape3DIndexService()

            if not os.path.exists(svc.index_path):
                return err(
                    "Index 3D inexistant. Veuillez indexer les modèles uploadés.",
                    400
                )

            results = svc.search(query_desc, top_k=top_k)

            os.unlink(tmp.name)

            return ok({
                "query_points": pts.shape[0],
                "top_k": top_k,
                "results": results
            })

        except Exception as e:
            return err("Recherche 3D échouée", 500, {"error": str(e)})


# ==========================================================
# STATISTIQUES (OPTIONNEL - APRÈS RECHERCHE)
# ==========================================================
class Stats3DResource(Resource):

    def post(self):
        try:
            data = request.get_json(silent=True)
            if not data:
                return err("JSON manquant", 400)

            query_label = data.get("query_label")
            results = data.get("results")

            if not query_label or not results:
                return err("query_label et results requis", 400)

            correct = sum(1 for r in results if r["label"] == query_label)
            precision = correct / max(1, len(results))

            return ok({
                "precision": round(precision, 3),
                "correct": correct,
                "total": len(results)
            })

        except Exception as e:
            return err("Statistiques 3D échouées", 500, {"error": str(e)})
