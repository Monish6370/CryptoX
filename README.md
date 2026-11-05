# CryptoX – Cryptocurrency Trading Platform

CryptoX is a PHP and MySQL-based cryptocurrency trading platform with user authentication, account management, balance requests, referrals, and an admin dashboard.

## Features

- User registration and login
- Secure password hashing
- User dashboard
- Profile management
- Password recovery
- Recovery key generation
- Balance management
- Referral system
- Admin dashboard
- Trade management

## Technologies Used

- PHP
- MySQL
- HTML
- CSS
- JavaScript
- Bootstrap (if used in the project)

## Requirements

- XAMPP
- PHP
- MySQL
- Web browser

## Installation and Setup

### 1. Download the Project

Download or clone this repository and place the project folder inside XAMPP's `htdocs` directory.

### 2. Start XAMPP

Start Apache and MySQL from the XAMPP Control Panel.

### 3. Create the Database

1. Open `http://localhost/phpmyadmin/`.
2. Import the file `database/schema.sql`.
3. The database name is `crypto_db`.

### 4. Configure Database Connection

1. Copy `db.example.php`.
2. Rename the copied file to `db.php`.
3. Open `db.php` and enter your local database credentials.

Example:

- Host: `localhost`
- Username: `root`
- Password: Your local MySQL password
- Database: `crypto_db`

**Important:** Never upload your actual `db.php` file or real database credentials to GitHub.

### 5. Run the Project

Open the following URL in your browser:

`http://localhost/crypto/`

If you use a different project folder name, replace `crypto` in the URL accordingly.

## Database

The repository contains `database/schema.sql`, which provides the database structure without actual user records.

Register a new account after importing the database.

## Security

- Database configuration is excluded from the public repository.
- The example configuration uses placeholder credentials.
- Actual database records are not included.

## Author

**Monish**

GitHub: [Monish6370](https://github.com/Monish6370)