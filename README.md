# ♻️ Smart Household Waste Management System

A web-based information system for managing household waste collection, transportation scheduling, recyclable tracking, and community Q&A — built with HTML, CSS, JavaScript, PHP, and MySQL.

---

## 👥 Team Members

- Felix Tay
- Gregory Orlando
- Michiko Liunaldi
- Zenelgen Kholee

---

## 📋 System Features

| Module | Description |
|---|---|
| User Authentication | Household and Admin registration, login, logout |
| Data Management | Full CRUD on households, waste bins, collections, transportation |
| Search & Retrieval | Real-time search and filtering across all modules |
| Q&A Forum | Households post questions, admins reply |
| Analytics Dashboard | Charts for collection trends, recyclable types, toxic waste, leaderboard, bin usage |

---

## 🛠️ Technology Stack

- **Frontend:** HTML5, CSS3, JavaScript (Vanilla)
- **Backend:** PHP 8.2
- **Database:** MySQL 8.0
- **Server:** Apache (XAMPP)
- **Charts:** Chart.js

---

## ⚙️ Requirements

- [XAMPP](https://www.apachefriends.org) (Apache + MySQL + PHP 8.x)
- A modern web browser (Chrome, Firefox, Edge)

---

## 🚀 Setup and Installation

### Step 1 — Clone the repository

```bash
git clone https://github.com/felix685/smart-household-waste-management.git
```

Or download the ZIP from GitHub and extract it.

### Step 2 — Move files to XAMPP

Copy the project folder into your XAMPP htdocs directory:

```
C:\xampp\htdocs\smart-household-waste-management\
```

### Step 3 — Start XAMPP

Open XAMPP Control Panel and start **Apache** and **MySQL**.

### Step 4 — Create the database

1. Go to `http://localhost/phpmyadmin`
2. Click **New** → name it `waste_db` → set collation to `utf8mb4_general_ci` → click **Create**
3. Select `waste_db` → click the **SQL** tab
4. Open `sql/schema.sql`, copy all contents, paste into the SQL box, click **Go**

### Step 5 — Configure database connection

Open `api/config.php` and update if needed:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'waste_db');
define('DB_USER', 'root');
define('DB_PASS', '');  // XAMPP default is empty
```

### Step 6 — Run the application

Open your browser and go to:

```
http://localhost/smart-household-waste-management/index.html
```

---

## 🔑 Default Login Credentials

After running the SQL schema, use these to log in:

| Role | Email | Password |
|---|---|---|
| Household | chen@mail.com | *(register a new account or use debug.php to reset)* |
| Admin | admin@wastesystem.com | *(register a new admin account)* |

> **Note:** Register a new account through the login page for reliable authentication.

---

## 📁 Project Structure

```
smart-household-waste-management/
├── index.html          # Login & Register page
├── dashboard.html      # Household portal
├── admin.html          # Admin panel
├── api/
│   ├── config.php      # Database configuration
│   ├── auth.php        # Authentication (login/register/logout)
│   ├── households.php  # Household CRUD
│   ├── wastebins.php   # Waste bin CRUD
│   ├── transportation.php  # Transportation scheduling
│   ├── collections.php     # Collection records
│   ├── qa.php          # Q&A forum
│   └── analytics.php   # Analytics data endpoints
├── css/
│   └── style.css       # Main stylesheet
├── js/
│   └── utils.js        # Shared JS utilities
└── sql/
    └── schema.sql      # Database schema and seed data
```

---

## 📊 Database Schema

The system uses 9 tables:

- `Admin` — Admin accounts
- `Household` — Household user accounts
- `WasteBin` — Waste bin locations and types
- `Transportation` — Pickup scheduling
- `Recyclable_Type` — Categories of recyclable materials
- `Collection_Record` — Waste collection records
- `Collection_Recyclable` — Junction table (collection ↔ recyclable type)
- `QA` — Household questions
- `QA_Reply` — Admin replies to questions
