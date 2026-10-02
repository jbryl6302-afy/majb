# Docker Setup for Blood Bank on Render

## Files added:
- `Dockerfile` - Builds the container with Apache + PHP + MariaDB + Python
- `start.sh` - Starts all services (MySQL, Flask API, Apache)
- `init.sql` - Database schema and default data
- `.dockerignore` - Excludes unnecessary files from build

## Steps to deploy on Render:

1. Add these 4 files to your project root (same level as `index.php`)

2. In Render Dashboard:
   - Create **New Web Service**
   - Connect your GitHub repo
   - Select **Docker** as environment
   - Set:
     - **Build Command:** (leave empty, Docker handles it)
     - **Start Command:** (leave empty, CMD in Dockerfile handles it)
   - Click Deploy

3. After deploy:
   - Visit your Render URL
   - Go to `/setup.php` to verify database (optional, already initialized)
   - Login with: `admin@bloodbank.com` / `password`

## What's running inside the container:
- **Port 80** → Apache + PHP (the main website)
- **Port 5000** → Flask AI API (predictions)
- **MariaDB** → MySQL database (internal, no external port)

## Notes:
- The AI model trains on startup using synthetic data (since BloodRequests table is empty initially)
- You can add real data through the web interface, then restart to retrain the model
- All data is stored inside the container (ephemeral). For production, connect Render PostgreSQL instead.
