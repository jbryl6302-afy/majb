#!/usr/bin/env python3
"""Blood Bank AI Model - Training Script"""

import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor
from sklearn.model_selection import train_test_split
import pickle
import mysql.connector
import sys

print("=" * 50)
print("Blood Bank AI - Model Training")
print("=" * 50)

try:
    db = mysql.connector.connect(host="localhost", user="root", password="", database="bloodbank2_db")
    print("Connected to MySQL database")
except Exception as e:
    print(f"Database connection failed: {e}")
    db = None

if db:
    query = """
    SELECT DATE_FORMAT(request_date, '%%Y-%%m') as month, patient_blood_type, COUNT(*) as request_count, SUM(units_needed) as total_units
    FROM BloodRequests WHERE status = 'Fulfilled' GROUP BY DATE_FORMAT(request_date, '%%Y-%%m'), patient_blood_type ORDER BY month
    """
    df = pd.read_sql(query, db)

if db is None or len(df) < 10:
    print("Generating synthetic training data...")
    data = []
    for month in pd.date_range('2024-01', '2025-12', freq='M'):
        for bt in ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']:
            base = np.random.randint(5, 30)
            data.append({'month': month.strftime('%Y-%m'), 'patient_blood_type': bt, 'request_count': base, 'total_units': base * np.random.randint(1, 4)})
    df = pd.DataFrame(data)

print(f"Training data: {len(df)} records")

blood_type_map = {'A+':0, 'A-':1, 'B+':2, 'B-':3, 'AB+':4, 'AB-':5, 'O+':6, 'O-':7}
df['blood_type_encoded'] = df['patient_blood_type'].map(blood_type_map)
df['month_num'] = pd.to_datetime(df['month']).dt.month
df['year'] = pd.to_datetime(df['month']).dt.year

X = df[['blood_type_encoded', 'month_num', 'year', 'request_count']]
y = df['total_units']

X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)
model = RandomForestRegressor(n_estimators=100, random_state=42)
model.fit(X_train, y_train)

score = model.score(X_test, y_test)
print(f"Model R2 Score: {score:.3f}")

with open('model.pkl', 'wb') as f:
    pickle.dump(model, f)

print("Model saved to model.pkl")
print("=" * 50)
