import os
from flask import Flask
from flask_restful import Api
from flask_cors import CORS
from werkzeug.exceptions import HTTPException

from config import Config
from services.yolo_service import YoloService
from ressources.health import HealthResource
from ressources.detect import DetectResource
from utils.responses import err

from ressources.descriptors import DescribeResource
from ressources.search import IndexAddResource, SearchSimilarResource
from services.index_service import FaissIndexService


def create_app():
    app = Flask(__name__)
    app.config.from_object(Config)

    # Ensure dirs exist
    os.makedirs(app.config["UPLOAD_DIR"], exist_ok=True)
    os.makedirs(app.config["TMP_DIR"], exist_ok=True)

    # Upload limit
    app.config["MAX_CONTENT_LENGTH"] = Config.MAX_CONTENT_LENGTH

    # CORS
    CORS(app)

    api = Api(app)

    # Services
    yolo_service = YoloService(app.config["WEIGHTS_PATH"])
    index_service = FaissIndexService(base_dir=os.path.join(os.path.dirname(__file__), "data", "faiss"))


    # Routes
    api.add_resource(HealthResource, "/health")
    api.add_resource(
        DetectResource,
        "/detect",
        resource_class_kwargs={"yolo_service": yolo_service}
    )
    
    api.add_resource(DescribeResource, "/describe")

    api.add_resource(
        IndexAddResource, "/index/add",
        resource_class_kwargs={"index_service": index_service}
    )
    api.add_resource(
        SearchSimilarResource, "/search-similar",
        resource_class_kwargs={"index_service": index_service}
    )
    
    index_service = FaissIndexService(
    base_dir=os.path.join(os.path.dirname(__file__), "data", "faiss"),
    preload=True
    )

    
    @app.errorhandler(Exception)
    def handle_exception(e):
        if isinstance(e, HTTPException):
            return err(e.description, e.code)
        return err("Internal server error", 500, {"error": str(e)})

    return app

if __name__ == "__main__":
    app = create_app()
    app.run(host=Config.HOST, port=Config.PORT, debug=Config.DEBUG)
