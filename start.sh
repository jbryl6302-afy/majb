#!/bin/bash
set -e

echo "========================================"
echo "Blood Bank 2 - Starting System..."
echo "========================================"

rm -f /var/www/html/index.html

# ---------- 1) الموديل: نستخدم model.pkl الموجود، ولا نعيد التدريب ----------
cd /var/www/html/ai_model

if [ ! -f "model.pkl" ]; then
    echo "model.pkl missing, creating fallback model..."
    python3 -c "
import pickle
import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor

types = ['A+','A-','B+','B-','AB+','AB-','O+','O-']
data = []
for month in pd.date_range('2024-01-01', '2025-12-01', freq='MS'):
    for bt in types:
        base = np.random.randint(5, 30)
        data.append({'blood_type_encoded': types.index(bt),
                     'month_num': month.month, 'year': month.year,
                     'request_count': base, 'total_units': base * 2})
df = pd.DataFrame(data)
X = df[['blood_type_encoded', 'month_num', 'year', 'request_count']]
y = df['total_units']
model = RandomForestRegressor(n_estimators=100, random_state=42)
model.fit(X, y)
with open('model.pkl', 'wb') as f:
    pickle.dump(model, f)
print('Fallback model created successfully')
" || echo "WARNING: could not create fallback model"
fi

echo "Model ready!"

# ---------- 2) خدمة الـ AI على المنفذ 5000 (داخلي) ----------
echo "Starting AI API on port 5000..."
gunicorn --bind 127.0.0.1:5000 predict:app \
    --daemon \
    --workers 1 \
    --timeout 60 \
    --access-logfile /var/log/gunicorn.log \
    --error-logfile /var/log/gunicorn.error.log \
    --capture-output \
    --enable-stdio-inheritance

sleep 2

if pgrep -f "gunicorn" > /dev/null; then
    echo "AI API is running on port 5000!"
else
    echo "Gunicorn failed to start"
    cat /var/log/gunicorn.error.log || true
fi

# ---------- 3) Apache على المنفذ الذي تحدده المنصة (PORT) ----------
APP_PORT="${PORT:-80}"
echo "Configuring Apache to listen on port ${APP_PORT}..."
sed -i "s/^Listen .*/Listen ${APP_PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${APP_PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "Starting Apache..."
service apache2 restart

echo ""
echo "========================================"
echo "Blood Bank 2 is running on port ${APP_PORT}"
echo "========================================"

tail -f /var/log/apache2/error.log /var/log/gunicorn.error.log