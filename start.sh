#!/bin/bash
set -e

echo "========================================"
echo "Blood Bank 2 - Starting System..."
echo "========================================"

rm -f /var/www/html/index.html

echo "Starting MariaDB..."
service mariadb start

echo "Waiting for MariaDB..."
for i in {1..30}; do
    if mysqladmin ping --silent; then
        echo "MariaDB is ready!"
        break
    fi
    sleep 1
done

echo "Configuring MariaDB root access..."
mysql -u root -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('');" 2>/dev/null || true
mysql -u root -e "UPDATE mysql.user SET plugin='mysql_native_password' WHERE User='root' AND Host='localhost';" 2>/dev/null || true
mysql -u root -e "FLUSH PRIVILEGES;" 2>/dev/null || true

echo "Creating database bloodbank2_db..."
mysql -u root -e "CREATE DATABASE IF NOT EXISTS bloodbank2_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" || true

echo "Importing database schema..."
mysql -u root bloodbank2_db < /var/www/html/init.sql || {
    echo "Could not import init.sql, database might already have schema"
}

echo "Training AI model..."
cd /var/www/html/ai_model
sed -i 's/bloodbank_db/bloodbank2_db/g' train_model.py 2>/dev/null || true

python3 train_model.py || {
    echo "train_model.py failed, creating fallback model..."
    python3 -c "
import pickle
import pandas as pd
import numpy as np
from sklearn.ensemble import RandomForestRegressor

data = []
for month in pd.date_range('2024-01', '2025-12', freq='M'):
    for bt in ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']:
        base = np.random.randint(5, 30)
        data.append({'blood_type_encoded': ['A+','A-','B+','B-','AB+','AB-','O+','O-'].index(bt), 
                     'month_num': month.month, 'year': month.year, 'request_count': base, 'total_units': base * 2})
df = pd.DataFrame(data)
X = df[['blood_type_encoded', 'month_num', 'year', 'request_count']]
y = df['total_units']
model = RandomForestRegressor(n_estimators=100, random_state=42)
model.fit(X, y)
with open('model.pkl', 'wb') as f:
    pickle.dump(model, f)
print('Fallback model created successfully')
"
}

if [ ! -f "/var/www/html/ai_model/model.pkl" ]; then
    echo "model.pkl missing! Creating emergency fallback..."
    cd /var/www/html/ai_model
    python3 -c "
import pickle
import numpy as np
from sklearn.ensemble import RandomForestRegressor
model = RandomForestRegressor(n_estimators=5, random_state=42)
model.fit(np.array([[0,1,2024,10]]), np.array([15]))
with open('model.pkl', 'wb') as f:
    pickle.dump(model, f)
print('Emergency model created')
"
fi

echo "Model ready!"

echo "Starting AI API on port 5000..."
cd /var/www/html/ai_model
gunicorn --bind 0.0.0.0:5000 predict:app \
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

echo "Starting Apache..."
service apache2 restart

echo ""
echo "========================================"
echo "Blood Bank 2 is running!"
echo "========================================"
echo "   Website: http://localhost"
echo "   AI API:  http://localhost:5000"
echo ""
echo "   Default login: admin@bloodbank2.com / password"
echo ""

tail -f /var/log/apache2/error.log /var/log/gunicorn.error.log
