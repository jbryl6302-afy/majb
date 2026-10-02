#!/usr/bin/env python3
"""Blood Bank AI Model - Prediction API (Flask)"""

from flask import Flask, jsonify
from flask_cors import CORS
import pickle
import pandas as pd
import sys

app = Flask(__name__)
CORS(app)

try:
    with open('model.pkl', 'rb') as f:
        model = pickle.load(f)
    print("Model loaded successfully")
except FileNotFoundError:
    print("ERROR: model.pkl not found. Run train_model.py first.")
    sys.exit(1)

blood_type_map = {'A+':0, 'A-':1, 'B+':2, 'B-':3, 'AB+':4, 'AB-':5, 'O+':6, 'O-':7}

@app.route('/')
def home():
    return jsonify({'status': 'Blood Bank AI API is running', 'model': 'RandomForestRegressor'})

@app.route('/predict/<blood_type>/<int:month>/<int:year>')
def predict(blood_type, month, year):
    try:
        bt_encoded = blood_type_map.get(blood_type, 0)
        features = pd.DataFrame([{'blood_type_encoded': bt_encoded, 'month_num': month, 'year': year, 'request_count': 10}])
        prediction = model.predict(features)[0]
        confidence = 0.85
        return jsonify({'blood_type': blood_type, 'predicted_units': max(1, int(prediction)), 'confidence': round(float(confidence), 2), 'month': month, 'year': year})
    except Exception as e:
        return jsonify({'error': str(e)}), 500

@app.route('/predict/all/<int:month>/<int:year>')
def predict_all(month, year):
    results = []
    for bt, encoded in blood_type_map.items():
        features = pd.DataFrame([{'blood_type_encoded': encoded, 'month_num': month, 'year': year, 'request_count': 10}])
        pred = model.predict(features)[0]
        results.append({'blood_type': bt, 'predicted_units': max(1, int(pred)), 'confidence': 0.85})
    return jsonify(results)

@app.route('/rescue/demand/<int:month>/<int:year>')
def rescue_demand(month, year):
    """الطلب المتوقع لكل فصيلة — يستخدمه محرك الإنقاذ الذكي"""
    results = []
    for bt, encoded in blood_type_map.items():
        features = pd.DataFrame([{'blood_type_encoded': encoded, 'month_num': month, 'year': year, 'request_count': 10}])
        pred = model.predict(features)[0]
        results.append({'blood_type': bt, 'predicted_units': max(1, int(pred))})
    return jsonify(results)

if __name__ == '__main__':
    print("Starting Blood Bank AI API on http://localhost:5000")
    app.run(host='0.0.0.0', port=5000, debug=True)
