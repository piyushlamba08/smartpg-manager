# 🏢 SmartPG Manager — Modern Hostel & Rental Management System

[![PHP Version](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net)
[![Frontend](https://img.shields.io/badge/Frontend-Vanilla_HTML5_%2F_CSS3_%2F_JS-E34F26?style=for-the-badge&logo=html5&logoColor=white)](https://developer.mozilla.org)
[![Database](https://img.shields.io/badge/Database-Zero--Config_Flat--File_JSON-4EAA25?style=for-the-badge)](https://github.com)
[![License](https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge)](LICENSE)

An ultra-fast, zero-database setup, modern Rental & Paying Guest (PG) Management System. Built with **Vanilla PHP RESTful API** and a sleek, responsive Single Page Application (SPA) frontend.

---

## ✨ Key Features

- 📊 **Real-Time Dashboard & Analytics**:
  - Total Tenants & Occupied Rooms count.
  - Target/Expected Monthly Revenue calculation.
  - Collected Rent vs. Overdue Pending Balances.
  - Dynamic Month Selector to switch historical & future billing cycles.

- 👥 **Smart Tenant Directory**:
  - Live instant search (by Name, Room Number, or Phone).
  - Quick status filtering: **All**, **Paid in Full**, **Partial (Due)**, and **Unpaid**.
  - One-click Actions: Collect Rent, Edit Tenant details, and Delete.

- 💰 **Partial & Advance Payment Ledger**:
  - Supports installment payments (e.g. rent ₹6,000 paid as ₹3,500 now, ₹2,500 balance remaining).
  - Automatically calculates remaining balance due or extra advance credit.
  - Multiple payment entries per tenant per month.

- 🖨️ **Official Rent Payment Receipts**:
  - Auto-generated receipt numbering (`RCP-YYYYMM-XXXX`).
  - Dynamic **Number-to-Words INR conversion** (e.g. *"Six Thousand Rupees Only"*).
  - Official Watermark badges (`PAID`, `PARTIAL`, `ADVANCE`).
  - One-click **Download / Save as PDF** & **Offline Slip (.txt)** download.

- 🔒 **Zero-Config Flat-File Database with PHP Security Guard**:
  - Runs completely plug-and-play without MySQL server configuration.
  - Stored inside `.php` data files with `http_response_code(403); exit;` guard line preventing unauthorized direct web browser access.
  - Concurrency protected with `LOCK_EX` file locks.
  - Protected API routes with `X-API-KEY` header validation.

---

## 🚀 Quick Start / How to Run Locally

### Prerequisites
- PHP 8.0 or higher installed.
- Any modern web browser (Chrome, Edge, Brave, Firefox, Safari).

### 1. Clone the repository
```bash
git clone https://github.com/<your-username>/smartpg-manager.git
cd smartpg-manager
```

### 2. Start the built-in PHP development server
```bash
php -S localhost:8000
```

### 3. Open in Browser
Visit [http://localhost:8000/index.html](http://localhost:8000/index.html) in your browser.

---

## 📁 Project Structure

```
├── api.php             # Core RESTful PHP API (CRUD, Routes, Stats, Receipts)
├── index.html          # Modern Single Page Application UI & Modals
├── tenants.data.php    # Secure Flat-file JSON database for Tenants
├── payments.data.php   # Secure Flat-file JSON database for Transactions
├── .gitignore          # Git ignore rules
└── README.md           # Project Documentation & Guide
```

---

## 🛠️ API Documentation

All requests require the `X-API-KEY: pg123secret` header.

| Endpoint | Method | Description |
| :--- | :--- | :--- |
| `/api.php?resource=tenants` | `GET` | List all registered tenants |
| `/api.php?resource=tenants` | `POST` | Add a new tenant |
| `/api.php?resource=tenants&id={id}` | `PUT` | Update tenant details |
| `/api.php?resource=tenants&id={id}` | `DELETE` | Delete tenant |
| `/api.php?resource=payments` | `GET` | Fetch transaction ledger (supports `?month=YYYY-MM`) |
| `/api.php?resource=payments` | `POST` | Record payment & generate receipt |
| `/api.php?resource=stats&month={YYYY-MM}` | `GET` | Calculate month-wise financial summary |

---

## 📄 License
This project is open-source and available under the **MIT License**.
