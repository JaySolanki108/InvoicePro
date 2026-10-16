# InvoicePro – Invoice Management System

InvoicePro is a web-based invoice management project developed using PHP and MySQL. It provides pages for user authentication, dashboard access, invoice creation, product management, and profile management.

## 🚀 Features

- **Login:** User login interface.
- **Sign Up:** User registration interface.
- **Dashboard:** Main application dashboard.
- **Create Invoice:** Interface for creating invoices.
- **Invoice History:** Page for invoice history.
- **Product Management:** Manage product-related information.
- **Profile Management:** User profile page.
- **Database Integration:** MySQL database connectivity using PHP.

## 🛠️ Technologies Used

- PHP
- HTML
- CSS
- MySQL 
- phpMyAdmin
- XAMPP

## 📁 Project Structure

```text
InvoicePro/
├── Create_Invoice.php
├── Dashboard.php
├── History_Invoice.php
├── Login.php
├── Products.php
├── Profile.php
├── Sign_Up.php
├── config.php
├── database.sql
├── index.php
└── README.md
```

## 🗄️ Database

The database is named `invoicepro` and includes the following tables:

| Table | Description |
|---|---|
| `users` | User and company information |
| `products` | Product details and pricing |
| `invoices` | Invoice records |
| `invoice_items` | Items linked to invoices |
| `bills` | Bill records |
| `bill_items` | Items linked to bills |

The `database.sql` file contains the database schema required to set up the project.

## ⚙️ Installation and Setup

### 1. Install XAMPP

Install XAMPP with Apache, PHP, and MySQL/MariaDB.

### 2. Copy the Project

Place the project folder inside the XAMPP `htdocs` directory.

Example:

```text
C:\xampp\htdocs\InvoicePro
```

### 3. Start XAMPP Services

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 4. Create and Import the Database

1. Open `http://localhost/phpmyadmin/`.
2. Create a database named `invoicepro`, if it does not already exist.
3. Select the database.
4. Open the **Import** tab.
5. Select the `database.sql` file.
6. Click **Import** or **Go**.

**Note:** Back up an existing database before importing if the SQL file contains commands that drop tables.

### 5. Configure Database Connection

Open `config.php` and set the database connection details according to your local environment.

Example for a default XAMPP setup:

```php
$host = "localhost";
$username = "root";
$password = "";
$database = "invoicepro";
```

These values are suitable only when they match your local MySQL configuration.

### 6. Run the Project

Open the following URL in your browser:

`http://localhost/InvoicePro/`

If your project folder has a different name, replace `InvoicePro` with that folder name.

## 🔐 Security

- Do not publish real customer information or sensitive credentials.
- Do not use default development database credentials in production.
- Keep production secrets outside publicly accessible source code.
- Back up your database before making schema changes.

## 🎯 Project Objective

The objective of InvoicePro is to demonstrate PHP web development and MySQL database integration for invoice-related information management.

## 👨‍💻 Project Information

- **Project Name:** InvoicePro
- **Backend:** PHP
- **Database:** MySQL / MariaDB
- **Local Server:** XAMPP

