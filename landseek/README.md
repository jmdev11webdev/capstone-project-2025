# 🌍 LANDSEEK: A Digital Marketplace for Land Hunting
**Capstone Project**  
In Partial Fulfillment of the Requirements for the Degree of  
**Bachelor of Science in Information Technology**

---

## 👥 Project Members
- Lahorra, Juan Miguel T.
- Bahillo, Kelvin Janssen B.
- Reales, Ewox T.

**Date:** 2025

---

# 📌 System Overview
LandSeek is composed of two separate systems:  

1. **LANDSEEK (User System)** – The main platform for land hunting, where buyers and sellers can list, browse properties, and have direct communication.  
2. **LANDSEEK: Admin Access** – A separate administrator portal for managing accounts, properties, and reports.  

## 🔐 LANDSEEK: Admin Access
The administrator system is managed in a separate repository.  
👉 [Go to LandSeek Admin Access](https://github.com/migslahorra/landseek_admins)
---

# 🏡 LANDSEEK (User System)

## 🎯 Overview
The **User System** is a prototype marketplace for buyers and sellers to connect directly. Sellers can upload properties, and buyers can search, filter, and send inquiries.  

### 🚀 Features
- **Property Listings** – Upload properties with complete details, images, and videos.  
- **Search & Filters** – Locate properties by classification, status, location, and price.  
- **Direct Inquiries** – Buyers can contact sellers within the platform.  
- **Activity Logs** – Records of property views and interactions.  
- **Prototype Deployment** – Runs locally via **XAMPP**, shareable via **ngrok**.  

### 🛠️ Tech Stack
- **Frontend:** HTML, CSS, JavaScript  
- **Backend:** PHP (traditional)  
- **Database:** MariaDB (SQL)  
- **Server:** XAMPP (Apache, PHP, MariaDB)  
- **Deployment (Prototype):** ngrok  

---

# 🔐 LANDSEEK: Admin Access

## 🎯 Overview
The **Admin Access** system is a separate platform that provides administrators with tools to manage the marketplace, enforce rules, and maintain system integrity.  

### 🚀 Features
- **User Account Management** – View, update, restrict, or ban accounts.  
- **Property Management** – Review, approve, or remove property listings.  
- **Report Handling** – Process reports submitted by users and take appropriate action.  
- **System Oversight** – Generate activity reports and monitor platform usage.  

### 🛠️ Tech Stack
- **Frontend:** HTML, CSS, JavaScript  
- **Backend:** PHP (traditional)  
- **Database:** MariaDB (SQL)  
- **Server:** XAMPP  

---

# ⚙️ Installation & Setup (Both Systems)
1. Install **XAMPP** on your local machine.  
2. Place the `landseek/` (user system) and `landseek_admin/` (admin system) folders inside the `htdocs` directory.  
3. Import the provided SQL database into **MariaDB** via phpMyAdmin or HeidiSQL.  
4. Start **Apache** and **MariaDB** from the XAMPP Control Panel.  
5. Access the systems:  
   - User System → `http://localhost/landseek/`  
   - Admin Access → `http://localhost/landseek_admins/`  
6. (Optional) Run ngrok for temporary public access:  
   ```bash
   ngrok http 80
