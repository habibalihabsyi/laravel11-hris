import sys, json, face_recognition, os

MODE = sys.argv[1]  # extract | match
IMAGE_PATH = sys.argv[2]
USER_ID = sys.argv[3] if len(sys.argv) > 3 else None

def extract_descriptor(image_path):
    image = face_recognition.load_image_file(image_path)
    encodings = face_recognition.face_encodings(image)
    if len(encodings) == 0:
        return None
    return encodings[0].tolist()

def match_descriptor(image_path, user_id):
    from dotenv import load_dotenv
    import mysql.connector

    load_dotenv()
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

    image = face_recognition.load_image_file(image_path)
    face_encodings = face_recognition.face_encodings(image)
    if len(face_encodings) == 0:
        return None
    current = face_encodings[0]

    best_id = None
    best_dist = 1.0
    for user in users:
        known = json.loads(user['face_descriptor'])
        dist = face_recognition.face_distance([known], current)[0]
        if dist < best_dist:
            best_dist = dist
            best_id = user['id']

    if best_dist <= 0.6:
        return best_id
    return None

if MODE == "extract":
    result = extract_descriptor(IMAGE_PATH)
    print(json.dumps(result))
elif MODE == "match":
    result = match_descriptor(IMAGE_PATH, USER_ID)
    print(json.dumps(result))
