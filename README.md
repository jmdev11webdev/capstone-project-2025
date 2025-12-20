## 📌 Short Repository Description

**LandSeek** is a capstone project developed as a digital marketplace for land hunting, enabling users to browse, inquire, and connect with land listings while providing administrators with a dedicated system to manage properties, users, and transactions efficiently. This project was presented during the Research Colloquium 2025 at Divine Word College of Legazpi, Albay, Philippines.

---

# 📘 README

## capstone-project-2025

## 📍 Project Title

**LandSeek: A Digital Marketplace for Land Hunting**

---

## 🧠 Project Overview

**LandSeek** is a web-based capstone project designed to modernize the land hunting process by providing a centralized digital platform for discovering, managing, and promoting land properties.

The system is divided into **two independent but connected applications**:

1. **LandSeek** – User-facing platform
2. **LandSeek Admins** – Administrative management system

This project was successfully defended during the **Research Colloquium (November 2025)** at **Divine Word College of Legazpi**, Albay, Philippines.

---

## 🏗️ System Architecture

### 1️⃣ LandSeek (User System)

The public-facing system used by clients and land seekers.

**Core Features:**

* Browse available land listings
* View detailed land information
* Search and filter properties
* Send inquiries via email (PHP Mailer)
* User-friendly interface
* Leaflet Map for convenient land seeking
  
**Target Users:**

* Buyers
* Investors
* Individuals searching for land properties

---

### 2️⃣ LandSeek Admins (Admin System)

A separate administrative system for managing platform data.

**Core Features:**

* Admin authentication & access control
* Manage land listings (Delete and resolve issues)
* Manage users and inquiries
* Database management via phpMyAdmin

**Target Users:**

* System administrators
* Project managers

---

## 🛠️ Technology Stack

### Frontend

* HTML5
* CSS3
* JavaScript

### Backend

* PHP (Native PHP)
* Node.js (for auxiliary features / scripts)
* PHP Mailer (email communication)

### Database

* MySQL / MariaDB
* phpMyAdmin (database GUI)

### Development Environment

* **XAMPP** (Apache, PHP, MySQL)
* No frameworks (pure/native stack)

---
```
---

## 🚀 Installation & Setup (XAMPP)

1. Install **XAMPP**
2. Copy project folders to:

   ```
   C:\xampp\htdocs\
   ```
3. Start **Apache** and **MySQL** in XAMPP
4. Import database:

   * Open `http://localhost/phpmyadmin`
   * Create database
   * Import `landseek.sql`
5. Access systems:

   * User system:

     ```
     http://localhost/landseek
     ```
   * Admin system:

     ```
     http://localhost/landseek_admins
     ```

---

## 📧 Email Configuration

* Uses **PHP Mailer**
* Configure SMTP credentials inside:
* Required for email verification

---

## 🎓 Academic Context

* **Course:** Capstone Project
* **Institution:** Divine Word College of Legazpi
* **Location:** Albay, Philippines
* **Year:** 2025
* **Status:** Research Colloquium Approved

---

## 📌 Notes

* This project uses **native PHP** (no Laravel)
* Designed for academic and prototype purposes
* Can be extended to modern stacks (Laravel, Docker, APIs)

---

## 📜 License

This project is intended for **academic use only**.
All rights reserved to the developers.

---
