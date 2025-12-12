from flask_restful import Resource
from utils.responses import ok

class HealthResource(Resource):
    def get(self):
        return ok({"status": "ok"})
