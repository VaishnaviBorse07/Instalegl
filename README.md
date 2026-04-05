# Instalegl

Welcome to the Instalegl project! This is a web application built using HTML, JS for the frontend, and PHP for backend processing. 

## Prerequisites
Before you start, make sure you have the following tools installed on your local machine:
- **PHP** (v7.4 or v8.x recommended)
- **MySQL Database Server** (This typically comes bundled if you install XAMPP, WAMP, or MAMP)
- **Git** (for version control)

## First-Time Environment Setup

### 1. Database Setup
1. Open your local MySQL management tool (such as phpMyAdmin, MySQL Workbench, or DBeaver).
2. Create a new, empty database. For example: `u721128021_instalegl_db`.
3. Locate the SQL dump file located in `database/instalegl_db.sql` (and any other related migration scripts).
4. Import the SQL file(s) into your newly created database to establish the correct table structure.

### 2. Configure Environment Variables
This project uses a `.env` file to securely define configurations like database connections and SMS APIs without exposing them in the general code structure.

1. Create a file named `.env` in the root folder of the project.
2. Put the following configuration variables inside, updating the passwords or API keys as needed:

```env
INSTALEGL_DB_HOST=localhost
INSTALEGL_DB_NAME=u721128021_instalegl_db
INSTALEGL_DB_USER=u721128021_instalegl
INSTALEGL_DB_PASS=Instalegl@123

INSTALEGL_SMS_SERVICE=brevo
INSTALEGL_SMS_FROM=Instalegl
INSTALEGL_BREVO_API_KEY=[YOUR_BREVO_API_KEY_HERE]
INSTALEGL_BREVO_SENDER_NAME=Instalegl
```

> **Warning:** Never commit your actual `.env` file! Always ensure `.env` is listed inside your project's `.gitignore` file to prevent leaking sensitive keys on GitHub.

## Running the Application Locally

You can use PHP's built-in development server to run this application quickly. No Apache/Nginx required just for testing the views!

1. Open your terminal or command prompt.
2. Ensure you are in the project's root directory:
   ```bash
   cd path/to/instalegl
   ```
3. Start the server on port 8000:
   ```bash
   php -S localhost:8000
   ```
4. Open your web browser and go to:
   👉 **[http://localhost:8000](http://localhost:8000)**

## Project Structure Overview
- `/admin/` - Files relating to the administration dashboard and login.
- `/backend/` - The core PHP processing files, OTP handling, and API connections.
- `/database/` - Stored database `.sql` schemas and migrations.
- `/uploads/` - Where dynamically uploaded documents are stored.
- `*.html` - User-facing core frontend pages (Index, About, Contact, etc.).
