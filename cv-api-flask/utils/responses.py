from flask import jsonify

def ok(data=None, status_code=200):
    payload = {"success": True}
    if data is not None:
        payload.update(data)
    return payload, status_code

def err(message="Error", status_code=400, details=None):
    payload = {"success": False, "message": message}
    if details is not None:
        payload["details"] = details
    return payload, status_code

