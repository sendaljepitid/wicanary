# WiCanary 📶: Business WiFi Security Monitoring System

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT) ## 📝 Introduction

**WiCanary** is a comprehensive solution designed to monitor enterprise WiFi networks for potential security threats in real-time. It utilizes dedicated IoT sensors (ESP32-based) deployed across your premises to analyze wireless traffic and detect common WiFi-based Denial of Service (DoS) attacks. Detected incidents are reported to a central web dashboard for visualization, analysis, and management.

---

## 🎯 The Problem: Hidden WiFi Threats

Standard network security tools often focus on wired traffic, leaving a significant blind spot in the wireless domain. WiFi networks are inherently vulnerable to various attacks that can disrupt business operations, compromise data, or facilitate unauthorized access. These include:

* **Denial of Service (DoS):** Attacks like Deauthentication/Disassociation Floods can disconnect legitimate users, crippling productivity. Beacon, Probe, Auth/Assoc, and RTS/CTS floods can overwhelm access points.
* **Evil Twins & Rogue APs:** Malicious access points mimicking legitimate networks to steal credentials.
* **Lack of Visibility:** Difficulty in monitoring and quickly identifying the source and nature of wireless threats across multiple locations.

---

##💡 Our Solution: Proactive Wireless Detection

WiCanary tackles these challenges by providing:

1.  **Dedicated Sensors:** Low-cost ESP32 sensors actively monitor WiFi channels (2.4GHz) for suspicious packet patterns indicative of common DoS floods (Deauth, Beacon, Probe Req, Auth Req, Assoc Req, RTS, CTS).
2.  **Centralized Dashboard:** A multi-tenant web application (PHP & MySQL) receives real-time alerts from sensors.
3.  **Real-time Alerts & Visualization:** The dashboard displays attack logs, sensor status/location on a map, and trend graphs. Physical sensors also have a buzzer for immediate local alerts.
4.  **Configurable Thresholds:** Detection sensitivity can be adjusted per sensor via the web dashboard.
5.  **Multi-Tenant Architecture:** Designed for service providers or large organizations to manage multiple clients/departments with isolated data and user roles (Administrator, Auditor, Operator).
6.  **User-Friendly Sensor Setup:** Utilizes WiFiManager for easy initial WiFi configuration via a captive portal – no hardcoding credentials.

---

## ✨ Key Features

* **Detection:** Identifies 7 common WiFi DoS flood attacks.
* **Real-time Logging:** Incidents logged instantly to the central database.
* **Web Dashboard:**
    * Multi-tenant client management (Super Admin).
    * Client-specific views with data isolation.
    * User role management (Admin, Auditor, Operator) per client.
    * Sensor management (add, edit configuration, view status).
    * Attack log viewer.
    * Geographic map of sensors (LeafletJS).
    * Attack trend visualization (Chart.js).
    * PDF report generation.
    * User profile management (password change).
* **Sensor Features:**
    * ESP32 based.
    * WiFiManager for easy setup.
    * Secure API communication with unique sensor tokens.
    * Remote configuration updates from the server.
    * Buzzer for local alerts.
    * Channel hopping for broader monitoring coverage.
* **Business Features:**
    * Subscription package management (Basic, Medium, etc.) with sensor limits.
    * Automated email reminders for expiring subscriptions (via Cron Job).

---

## 🛠️ Technology Stack

* **Backend:** PHP, MySQL/MariaDB
* **Frontend:** HTML, CSS, JavaScript, Bootstrap 5, Chart.js, LeafletJS
* **Sensor:** ESP32 Microcontroller, Arduino Framework (C++)
* **Sensor Libraries:** WiFi, HTTPClient, ArduinoJson, WiFiManager, ESP-IDF WiFi functions
* **Email:** PHPMailer (via SMTP)
* **PDF Generation:** FPDF

---

## 🚀 Setup & Installation

### Server-Side (Web Application)

1.  **Requirements:** Web server (Apache/Nginx), PHP (with PDO MySQL extension), MySQL/MariaDB database.
2.  **Clone Repository:** `git clone https://github.com/your-username/wicanary-web-app.git /path/to/your/webroot/wicanary_bisnis`
3.  **Database Setup:**
    * Create a MySQL database (e.g., `wicanary_business`).
    * Import the provided `.sql` file (`wicanary_business.sql` - *you should create this export*) into the database. This sets up tables and initial packages.
4.  **Configuration:**
    * Copy `db_config.php.example` to `db_config.php`.
    * Edit `db_config.php` with your database credentials (host, name, user, password).
5.  **(Optional) Composer:** If using PHPMailer via Composer, run `composer install` in the project root.
6.  **Super Admin:** Manually add a super admin user to the `super_admins` table or create a setup script. Remember to hash the password using `password_hash()`.
7.  **Cron Job:** Set up the `email_reminder.php` script to run daily via your server's Cron Job scheduler (refer to `email_reminder.php` comments for command example).
8.  **Web Server Config:** Ensure your web server points to the correct directory and allows `.htaccess` overrides if needed (though none are strictly required by this setup currently).

### Sensor-Side (ESP32)

1.  **Requirements:** Arduino IDE or PlatformIO, ESP32 board support installed, required libraries (`WiFiManager`, `ArduinoJson`).
2.  **Open Sketch:** Load the `ESP32_WiCanary_Client_Final_vX.ino` sketch.
3.  **Configure Server URL:** Modify the `serverName` constant to point to your web server's **correct path** where the PHP API files reside (e.g., `http://yourdomain.com/wicanary_bisnis`).
4.  **Flash:** Upload the sketch to your ESP32 board.
5.  **Initial WiFi Setup:** Follow the **User Guide** section on "Konfigurasi Awal Jaringan Sensor" using the WiFiManager captive portal ("WiCanary_Setup" hotspot).

---

## 📖 Usage

### Sensor

* Plug the sensor into a USB power source.
* Perform the initial WiFi setup via the "WiCanary_Setup" hotspot if needed.
* The sensor will automatically connect, check-in with the server, and start monitoring. The buzzer will sound if an attack is detected.

### Web Dashboard

1.  **Super Admin:** Access `super_login.php`. Manage clients (add/edit), view all system activity.
2.  **Client Users (Admin/Auditor/Operator):** Access `login.php`.
    * View attack logs, map, trends.
    * **Admin:** Add/edit sensors (respecting package limits), manage client users (up to limit).
    * **Auditor/Admin:** Generate PDF reports.
    * **All Roles:** Edit own profile (change password).

    <img width="951" height="414" alt="image" src="https://github.com/user-attachments/assets/0f647353-debf-4133-a108-c0287edbd694" />


---

## 🤝 Contributing (Optional)

Contributions are welcome! Please follow standard Git workflow (fork, branch, pull request).

---

## 📜 License (Optional)

This project is licensed under the MIT License - see the [LICENSE.md](LICENSE.md) file for details.

---

## 👤 Author

* **Budi Wibowo** - *Initial Work* - [Your GitHub Profile Link (Optional)]()

---
