# Deployment Guide — Free Cloud Hosting

## Recommended: Railway.app (Free — PHP + Google Sheets)

Railway gives a free subdomain: `https://tbi-mce-xxxx.up.railway.app`

---

## STEP 1 — Prepare Google Sheets (do this first)

1. Go to https://console.cloud.google.com
2. Create a new project → Name it "TBI Task Manager"
3. Search for **Google Sheets API** → Enable it
4. Go to **Credentials** → **+ Create Credentials** → **Service Account**
   - Name: `tbi-sheets-service`
   - Click Done
5. Click the new service account → **Keys** tab → **Add Key** → **JSON**
6. Download the JSON file → rename it `credentials.json`
7. **Create a Google Sheet:**
   - Go to sheets.google.com → New Spreadsheet
   - Name it "TBI Task Manager"
   - Copy the **Sheet ID** from the URL:  
     `https://docs.google.com/spreadsheets/d/`**`THIS_LONG_ID`**`/edit`
8. **Share the sheet** with the `client_email` from credentials.json → give **Editor** access

---

## STEP 2 — Push code to GitHub

On your computer (or in this terminal):

```bash
cd /home/mce/update_TBI/tbi_task_manager

# Initialize git
git init
git add .
git commit -m "Initial commit: TBI-MCE Task Manager"

# Create a repo on GitHub (github.com → New Repository)
# Name it: tbi-task-manager   (keep it Private)
# Then push:
git remote add origin https://github.com/YOUR_USERNAME/tbi-task-manager.git
git branch -M main
git push -u origin main
```

> **DO NOT push `credentials.json`** — it's in `.gitignore` already.

---

## STEP 3 — Deploy on Railway (Free)

1. Go to **https://railway.app** → Sign up with GitHub
2. Click **New Project** → **Deploy from GitHub repo**
3. Select your `tbi-task-manager` repository
4. Railway auto-detects the Dockerfile and starts building

### Set Environment Variables in Railway:

Click your service → **Variables** tab → Add:

| Variable | Value |
|----------|-------|
| `SPREADSHEET_ID` | (paste your Google Sheet ID) |
| `BASE_URL` | *(leave empty)* |
| `SMTP_USER` | your Gmail address |
| `SMTP_PASS` | your Gmail App Password |

### Upload credentials.json to Railway:

Since credentials.json can't go to GitHub, use Railway's file upload:
1. In Railway: **Settings** → **Source** → click your service
2. Go to **Files** tab (or use Railway CLI)
3. Upload `credentials.json` to `/app/config/credentials.json`

**OR use the Railway CLI:**
```bash
npm install -g @railway/cli
railway login
railway link  # link to your project
railway run -- cat /dev/null  # test connection
# Copy credentials.json to the Railway volume
railway volume add
```

**Easiest alternative:** Base64-encode credentials.json and store as env var:
```bash
base64 -w 0 config/credentials.json
# Paste the output as env var GOOGLE_CREDENTIALS_BASE64 in Railway
```
Then add to `config/config.php` (already done):
```php
// The config.php handles this automatically
```

### Get your live URL:

After deployment (2-3 min), Railway shows:  
`https://tbi-task-manager-production.up.railway.app`

---

## STEP 4 — Initialize the Database (Google Sheets)

Visit in your browser:
```
https://your-app.up.railway.app/setup/setup_sheets.php?key=TBI_SETUP_2024
```

This creates all sheets and sample data automatically.

> **Delete or block this URL after running!**  
> In Railway Variables add: `SETUP_DONE=1`

---

## STEP 5 — First Login

| Role | Username | Password |
|------|----------|----------|
| CEO Admin | `geetha` | `Admin@123` |
| COO Admin | `mohana` | `Admin@123` |
| Software | `darshan` | `Employee@123` |
| Finance | `ramya` | `Employee@123` |
| Innovation | `madhurya` | `Employee@123` |
| Support | `deeksha` | `Employee@123` |

**Change all passwords immediately after first login!**

---

## Alternative: Render.com (Also Free)

1. Go to **https://render.com** → Sign up
2. **New** → **Web Service** → Connect GitHub repo
3. Runtime: **Docker**
4. Set same environment variables as Railway
5. Free URL: `https://tbi-task-manager.onrender.com`

> Note: Render free tier sleeps after 15 min of inactivity (30s wake-up)

---

## Alternative: 000WebHost (Traditional PHP Hosting)

If you prefer simple file upload without Docker:

1. Go to **https://www.000webhost.com** → Sign up (free)
2. Create website → choose free subdomain like `tbi-mce.000webhostapp.com`
3. Go to **File Manager** → Upload all project files to `public_html/`
4. Edit `config/config.php` → set `BASE_URL` to `''`
5. Use 000webhost **PHP/MySQL** to run `composer install` via SSH  
   (or ask support to install vendor packages)
6. Upload `credentials.json` manually to `config/` folder

---

## Quick Summary

```
GitHub → Railway → Auto-deploy → Live URL in 3 minutes
```

Most important files for deployment:
- `Dockerfile` — builds PHP + Apache + Composer
- `docker/start.sh` — starts server on correct port  
- `railway.json` — Railway configuration
- `render.yaml` — Render.com configuration
- `config/config.php` — reads all secrets from env vars
```
