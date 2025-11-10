import sys, os, json
from deepface import DeepFace
import mysql.connector
import numpy as np
from dotenv import load_dotenv

MODE = sys.argv[1]  # extract | match
IMAGE_PATH = sys.argv[2]
USER_ID = sys.argv[3] if len(sys.argv) > 3 else None

load_dotenv()

def extract_descriptor(image_path):
    try:
        emb = DeepFace.represent(img_path=image_path, model_name='Facenet', enforce_detection=True)
        return emb[0]["embedding"]
    except Exception as e:
        print(json.dumps(None))
        sys.exit(0)

def match_descriptor(image_path):
    conn = mysql.connector.connect(
        host=os.getenv('DB_HOST', '127.0.0.1'),
        user=os.getenv('DB_USERNAME', 'root'),
        password=os.getenv('DB_PASSWORD', ''),
        database=os.getenv('DB_DATABASE', 'laravel')
    )
    cur = conn.cursor(dictionary=True)
    cur.execute("SELECT id, face_descriptor FROM users WHERE face_descriptor IS NOT NULL")
    users = cur.fetchall()
    conn.close()

    try:
        target = DeepFace.represent(img_path=image_path, model_name='Facenet')[0]["embedding"]
    except Exception:
        print(json.dumps(None))
        sys.exit(0)

    def distance(a, b):
        a, b = np.array(a), np.array(b)
        return np.linalg.norm(a - b)

    best_id, best_dist = None, 999
    for u in users:
        known = json.loads(u["face_descriptor"])
        dist = distance(known, target)
        if dist < best_dist:
            best_id, best_dist = u["id"], dist

    if best_dist <= 0.6:
        print(best_id)
    else:
        print(json.dumps(None))

if MODE == "extract":
    result = extract_descriptor(IMAGE_PATH)
    print(json.dumps(result))
elif MODE == "match":
    match_descriptor(IMAGE_PATH)
