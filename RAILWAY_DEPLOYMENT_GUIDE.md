# 🚀 How to Deploy SportSync to Railway (Step-by-Step Guide for Beginners)

> **Difficulty:** Very Easy (like following a recipe)  
> **Time needed:** About 15-20 minutes  
> **What you need:** A GitHub account and a Railway account (both free)

---

## 📋 What is Railway?

Railway is a website that runs your app on the internet automatically. You just give it your code, and it handles everything else.

---

## 🧭 The Big Picture (Read This First)

```
Your Computer (code) → GitHub (storage) → Railway (runs it live)
```

You'll do these 4 things:
1. **Push your code to GitHub** (upload it)
2. **Sign up for Railway** (create an account)
3. **Connect Railway to GitHub** (link them together)
4. **Add your settings** (tell Railway your passwords)

---

## ✅ Step 1: Push Your Code to GitHub

> **What this does:** Uploads your project to the internet so Railway can access it.

### 1.1 Create a GitHub account (if you don't have one)
1. Go to https://github.com
2. Click **"Sign up"**
3. Enter your email, create a password, pick a username
4. Verify your email

### 1.2 Create a new repository (a "folder" on GitHub)
1. Click the **"+" icon** (top-right corner) → **"New repository"**
2. For **"Repository name"**, type: `sportssync`
3. Make sure it's set to **"Public"** (it's free)
4. Click **"Create repository"**
5. **IMPORTANT:** Do NOT check "Add a README" or ".gitignore" — leave everything empty

### 1.3 Upload your code to GitHub
Open **Command Prompt** (search "cmd" in Windows Start menu), then type these commands one by one (press Enter after each):

```bash
cd "C:\Users\Administrator\Desktop\XAMPP MAIN FILE\htdocs\sportssync-laravel"
```

```bash
git remote add origin https://github.com/YOUR_USERNAME/sportssync.git
```
> ⚠️ Replace `YOUR_USERNAME` with your actual GitHub username

```bash
git push -u origin main
```

> **If it asks for login:** A GitHub window will pop up. Sign in to let it upload.

✅ **Done!** Your code is now on GitHub at: `https://github.com/YOUR_USERNAME/sportssync`

---

## ✅ Step 2: Create a Railway Account

> **What this does:** Gives you access to the platform that will run your app.

1. Go to https://railway.app
2. Click **"Start a New Project"** or **"Sign Up"**
3. Click **"Continue with GitHub"**
4. Authorize Railway to access your GitHub (click "Authorize" / "Allow")
5. You're now logged in! 🎉

---

## ✅ Step 3: Deploy Your Project on Railway

> **What this does:** Tells Railway to grab your code from GitHub and run it.

### 3.1 Create a new project
1. On Railway dashboard, click **"New Project"** button
2. Click **"Deploy from GitHub repo"**
3. If it asks you to install Railway on GitHub, click **"Install"** and select your `sportssync` repository
4. Select your **"sportssync"** repository from the list

### 3.2 Wait for the first build (it will fail — that's OK!)
Railway will start building your app. It will show an error because we haven't added a database yet. That's normal!

---

## ✅ Step 4: Add a MySQL Database

> **What this does:** Creates a database for your app to store data (users, games, etc.).

1. In your project dashboard, click **"New"** (the "+" button)
2. Select **"Database"** → **"MySQL"** (NOT PostgreSQL)
3. Wait about 30 seconds for it to be created
4. You'll see a green checkmark ✅ when it's ready

### ⭐ IMPORTANT: Railway will automatically create these variables for you

Once the MySQL database is added, Railway will automatically create these variables in your project:

| Variable | What it is |
|---|---|
| `MYSQLHOST` | The database server address (private) |
| `MYSQLPORT` | The port number (always 3306) |
| `MYSQLDATABASE` | The database name (usually "railway") |
| `MYSQLUSER` | The username (usually "root") |
| `MYSQLPASSWORD` | The password (a long random string) |
| `MYSQL_URL` | The full connection string (private) |
| `MYSQL_PUBLIC_URL` | The full connection string (public) |

**Your app's `docker-entrypoint.sh` already knows how to read these automatically!** So you don't need to manually copy database settings.

---

## ✅ Step 5: Add Your Settings (Environment Variables)

> **What this does:** Tells your app your secret keys and settings.

### 5.1 Go to the Variables tab
1. Click on your **app service** (the one with your code, NOT the database)
2. Go to the **"Variables"** tab
3. Click **"New Variable"**

### 5.2 Add these variables one by one

Click **"Add Variable"** for each one:

| Variable Name | Value |
|---|---|
| `APP_KEY` | `base64:0VX3PPtLVJXxJp2S+y5OzEL+hCWKFXPt/WIHslm/VEI=` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_URL` | *(leave empty for now, we'll fill it later)* |
| `SUPERADMIN_USERNAME` | `sportssyn@admin` |
| `SUPERADMIN_EMAIL` | `sportssyncsuper@gmail.com` |
| `SUPERADMIN_PASSWORD` | `BVBTDarts_super@123` |

**That's it!** You do NOT need to add `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, or `DB_PASSWORD` — Railway's MySQL database automatically creates `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD` for you, and your app's startup script (`docker-entrypoint.sh`) reads them automatically.

---

## ✅ Step 6: Add a Custom Domain (Optional but Recommended)

> **What this does:** Gives your app a real website address.

1. In your app service, go to **"Settings"** tab
2. Scroll to **"Domains"** section
3. Click **"Generate Domain"**
4. Railway will give you a URL like: `sportssync-production-xxxx.up.railway.app`
5. **Copy this URL**
6. Go back to **"Variables"** tab
7. Find `APP_URL` and set its value to your new URL (e.g., `https://sportssync-production-xxxx.up.railway.app`)
8. Click **"Update"**

---

## ✅ Step 7: Wait for Deployment to Finish

> **What this does:** Railway builds and starts your app automatically.

1. Go to the **"Deployments"** tab
2. You'll see a new deployment running
3. Wait for it to show **"Deployed"** or **"Running"** (green checkmark ✅)
4. This takes about 3-5 minutes

### What happens automatically (you don't need to do anything):
- ✅ Your `docker-entrypoint.sh` detects the MySQL variables (`MYSQLHOST`, etc.)
- ✅ It creates the `.env` file with the correct database settings
- ✅ It runs `php artisan migrate --force` to create all database tables
- ✅ It starts Nginx, PHP-FPM, and the WebSocket server

---

## ✅ Step 8: Test Your App! 🎉

1. Go to the URL Railway gave you (from Step 6)
2. You should see your SportSync app!
3. Try logging in with the super admin credentials:
   - **Email:** `sportssyncsuper@gmail.com`
   - **Password:** `BVBTDarts_super@123`

---

## 🔧 Troubleshooting

### ❌ "502 Bad Gateway" error
- Wait 2-3 minutes and refresh the page (PHP-FPM might still be starting)
- If it persists, go to **"Deployments"** tab and click **"Redeploy"**
- Check the **"Logs"** tab to see what's happening

### ❌ "No application encryption key" error
- Go to **"Variables"** tab
- Make sure `APP_KEY` is set to: `base64:0VX3PPtLVJXxJp2S+y5OzEL+hCWKFXPt/WIHslm/VEI=`
- Click **"Redeploy"**

### ❌ "Database connection failed" error
- Make sure you added a **MySQL** database (not PostgreSQL)
- Check that Railway's MySQL variables exist: Go to **"Variables"** tab and look for `MYSQLHOST`, `MYSQLPASSWORD`, etc.
- If they're missing, delete the database and add it again

### ❌ "Class not found" or "Composer" errors
- Go to **"Settings"** tab of your app service
- Scroll to **"Build & Deploy"** section
- Make sure **"Build Command"** is empty (Railway will use your Dockerfile)
- Make sure **"Start Command"** is empty

### ❌ App is stuck on "Building" for more than 10 minutes
- Go to **"Deployments"** tab
- Click **"Cancel"** on the current deployment
- Click **"Redeploy"**

### ❌ How to see what went wrong
1. Go to **"Deployments"** tab
2. Click on the latest deployment
3. Click **"View Logs"** or **"Logs"**
4. Look for red text or lines with "ERROR" — that tells you what failed

---

## 📝 Quick Reference Card

```
┌─────────────────────────────────────────────────────────────┐
│                    QUICK DEPLOYMENT CHECKLIST                │
├─────────────────────────────────────────────────────────────┤
│ [ ] 1. Push code to GitHub                                  │
│ [ ] 2. Create Railway account                               │
│ [ ] 3. Create new project → Deploy from GitHub              │
│ [ ] 4. Add MySQL database (NOT PostgreSQL)                  │
│ [ ] 5. Add environment variables: APP_KEY, APP_ENV, etc.    │
│ [ ] 6. Generate domain & set APP_URL                        │
│ [ ] 7. Wait for deployment to finish (auto-migrates!)       │
│ [ ] 8. Test your app!                                       │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎯 Summary

You did it! Here's what happened:

1. **GitHub** stores your code like a cloud folder
2. **Railway** reads your `Dockerfile` and builds your app automatically
3. **MySQL** (on Railway) stores all your data — Railway gives it variables like `MYSQLHOST`, `MYSQLPASSWORD` automatically
4. **Your `docker-entrypoint.sh`** automatically detects the MySQL variables and sets up everything
5. **Migrations run automatically** — you don't need to run them manually!
6. Your app is now live on the internet! 🌐

**Your app URL:** `https://your-app-name.up.railway.app`

---

> **Need help?** Railway has great docs at https://docs.railway.app  
> Or check your deployment logs in Railway's **"Deployments"** tab for error messages.