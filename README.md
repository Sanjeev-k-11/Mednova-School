# Mednova School - Comprehensive School Management System & CMS

![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg) ![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php) ![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?logo=mysql) ![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-v3-06B6D4?logo=tailwindcss) ![JavaScript](https://img.shields.io/badge/JavaScript-ES6-F7DF1E?logo=javascript)

**Mednova School** is a powerful, all-in-one School Management System (ERP) designed to automate and streamline the administrative and academic processes of educational institutions. Built with native PHP and MySQL, it features a modern, responsive interface powered by Tailwind CSS.

This system is not just an internal ERP; it also includes a fully integrated **Content Management System (CMS)**, allowing administrators to manage the public-facing school website directly from their dashboard without touching a single line of code.

---

### 🔴 **Live Demo**

**[Live ](https://skyschool.mednova.store/)**

*(Please provide default login credentials for different roles in the demo)*

---

## ✨ Key Features

The system is built on a modular, role-based architecture, providing specific functionalities for each user type.

### 👤 **Multi-Role Access Control**
-   **Super Admin:** Has ultimate control over the entire system, manages admins, and oversees all school-wide settings.
-   **Admin / Staff:** Manages day-to-day operations, including student admissions, fee collection, staff management, and website content.
-   **Principal:** Oversees academic and administrative functions, approves leaves, reviews discipline, and monitors school-wide progress.
-   **Teacher:** Manages classes, assignments, attendance, marks, and communicates with students.
-   **Student:** Accesses academic information, submits assignments, participates in forums, and views school updates.

### 📚 **Academic Management (LMS)**
-   **Class & Subject Management:** Create and manage classes, sections, and subjects with ease.
-   **Timetable Generation:** Intuitive interface to create and view class and teacher timetables.
-   **Examination & Marks:** Schedule exams, manage various exam types, and upload student marks.
-   **Attendance Tracking:** Mark and view daily student attendance with comprehensive reporting features.
-   **Assignments & Study Materials:** Create assignments with due dates and upload/share study materials by class and subject.
-   **Online Tests & Quizzes:** Conduct online tests with automated scoring and student attempt tracking.

### 💰 **Financial Management**
-   **Fee Structure & Collection:** Define fee types, assign fees to classes, and track student payments (unpaid, paid, partial).
-   **Staff Payroll:** Generate monthly salary slips for staff, including deductions and bonuses.
-   **Income & Expense Tracking:** Log all school income and expenses for complete financial oversight.
-   **Scholarship Management:** Create and assign scholarships to deserving students.

### 👨‍👩‍👧‍👦 **User & HR Management**
-   **Student Information System:** A comprehensive module to manage student admissions, profiles, and status (active/blocked).
-   **Staff Management:** Manage profiles for all staff members (Admins, Principals, Teachers).
-   **Department Management:** Organize teachers into departments with assigned Heads of Department (HODs).
-   **Leave Management:** A complete workflow for students and staff to apply for and get leaves approved.

### 💬 **Communication & Collaboration**
-   **Announcements & Events:** Post school-wide announcements and manage a dynamic event calendar.
-   **Integrated Chat:** Secure real-time chat between staff members and between students and their subject teachers.
-   **Student Forum:** A moderated discussion board for students to interact and learn collaboratively.
-   **Helpdesk System:** A ticketing system for students to raise queries directly to their teachers.

### 🌐 **Integrated Public Website CMS**
A standout feature that allows admins to manage the public-facing website's content without any coding.
-   **Homepage Management:** Customize the hero banner, about section, academic highlights, and more.
-   **Dynamic Page Content:** Visually edit the content for the "About Us," "Academics," "Admissions," and "Contact" pages.
-   **Gallery & News:** Upload photos and videos to the website gallery and post articles to the "Events & News" page.
-   **Contact Inquiries:** View and reply to inquiries submitted through the website's contact form directly from the admin panel.

### 🚌 **Additional Modules**
-   **Library Management:** Catalog books, manage borrowing records, and track fines.
-   **Transport Management:** Manage school van routes, fees, and assign students/staff.
-   **Extracurricular Activities:** Manage sports clubs, cultural programs, and competitions, including participant registration.
-   **Discipline Tracking:** A formal system for teachers to report and principals to review student indiscipline incidents.

---

## 🛠️ Technology Stack

-   **Backend:** **PHP 8.1+** (Native, Procedural & OOP)
-   **Database:** **MySQL 8.0+**
-   **Frontend:** HTML, CSS, JavaScript (ES6)
-   **UI Framework:** **Tailwind CSS v3**
-   **Server Environment:** XAMPP / WAMP (Apache, MySQL, PHP)

---

## 🚀 Getting Started

Follow these steps to set up the project locally.

### Prerequisites
-   A local server environment like [XAMPP](https://www.apachefriends.org/index.html) or WAMP.
-   A web browser (Chrome, Firefox, etc.).
-   A code editor (VS Code, Sublime Text, etc.).

### Installation Steps

1.  **Clone the Repository**
    ```bash
    git clone https://github.com/Sanjeev-k-11/mednova-school.git
    ```

2.  **Move to `htdocs`**
    Move the cloned project folder `mednova-school` into the `htdocs` directory of your XAMPP installation (e.g., `C:/xampp/htdocs/mednova-school`).

3.  **Start Services**
    Open the XAMPP Control Panel and start the **Apache** and **MySQL** services.

4.  **Create the Database**
    -   Open your web browser and navigate to `http://localhost/phpmyadmin/`.
    -   Click on the **"Databases"** tab.
    -   Create a new database and name it `mednova_school_db`.

5.  **Import the Database**
    -   Select the `mednova_school_db` database you just created.
    -   Click on the **"Import"** tab.
    -   Click "Choose File" and select the `sql1.sql` file located in the root of the project folder.
    -   Click **"Go"** at the bottom of the page to start the import.

6.  **Configure Database Connection**
    -   Navigate to the `database` folder in your project (`mednova-school/database/`).
    -   Open the database connection file (e.g., `db_connection.php`).
    -   Ensure the database credentials match your local setup:
      ```php
      $servername = "localhost";
      $username = "root";
      $password = ""; // Default is empty for XAMPP
      $dbname = "mednova_school_db";
      ```

7.  **Run the Application**
    Open your browser and navigate to `http://localhost/mednova-school/`. You should see the login page.

---

## 🔐 Default Login Credentials

Use the following credentials to test the different user roles:

| Role        | Username / ID         | Password  |
|-------------|-----------------------|-----------|
| Super Admin | `superadmin`          | `password`|
| Admin       | `admin101`            | `password`|
| Principal   | `principal1001`       | `password`|
| Teacher     | `teacher01`           | `password`|
| Student     | `REG001`              | `password`|

*(Note: Please update this table with the actual default credentials from your SQL import file.)*

---

## 🖼️ Screenshots

*(Add screenshots of your application here to give a visual preview.)*

**Example:**
*   **Admin Dashboard**
    <img width="1919" height="868" alt="image" src="https://github.com/user-attachments/assets/8c969c92-fb4f-4f1d-8d99-826c0976b4ad" />

*   **Student Portal**
    <img width="1918" height="861" alt="image" src="https://github.com/user-attachments/assets/d7ce2347-503f-46e0-9558-cf9254de2123" />

*   **Student Portal**
    <img width="1919" height="855" alt="image" src="https://github.com/user-attachments/assets/5dd262c0-4ca0-4b01-8d04-bd55d83a34ea" />
    <img width="1919" height="858" alt="image" src="https://github.com/user-attachments/assets/6576e63f-def2-4e07-8f66-e0927a3f5bc5" />

*   **Public Website CMS Editor**
    ![CMS Editor](link-to-your-screenshot.png)

---

## 📁 File Structure Overview

The project is organized into role-based directories for clarity and separation of concerns.

```
/mednova-school
├── admin/                # Admin panel files
├── database/             # Database connection logic
├── principle/            # Principal panel files
├── student/              # Student portal files
├── super_admin/          # Super Admin panel files
├── teacher/              # Teacher panel files
├── uploads/              # Directory for file uploads (images, documents)
├── user/                 # Public-facing website pages (About, Contact, etc.)
├── index.php             # Main entry point (Login page or Homepage)
├── login.php             # Login handler
├── logout.php            # Logout script
├── sql1.sql                # The complete database schema and data
└── README.md             # You are here
```

<div align="center">
  <h3>Connect with me:</h3>
  <p>
    <a href="https://github.com/Sanjeev-k-11" target="_blank"><img alt="Github" src="https://img.shields.io/badge/GitHub-100000?style=for-the-badge&logo=github&logoColor=white"></a>
    <a href="https://www.linkedin.com/in/sanjeevkumaryadav/" target="_blank"><img alt="LinkedIn" src="https://img.shields.io/badge/LinkedIn-0077B5?style=for-the-badge&logo=linkedin&logoColor=white"></a>
  </p>
</div>
   
