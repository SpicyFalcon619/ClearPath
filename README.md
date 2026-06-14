# ClearPath

ClearPath is a comprehensive, full-stack University Clearance System designed to streamline the graduation and departure process for students. It provides a seamless, dynamic experience for students, department administrators, and master administrators.

## Overview

The system eliminates paper-based clearance forms by digitizing the entire workflow. Students can track their clearance status in real-time, while department admins can efficiently manage queues, assign dues, and communicate directly with students.

It features three distinct user portals:
- **Student Portal**: Allows students to submit clearance applications, update their academic profile (course and batch), upload supporting documents, track departmental approvals in real-time, and download their final PDF Clearance Certificate.
- **Department Admin Portal**: A streamlined dashboard for department-specific admins (e.g., Library, Finance, Labs) to review applications, communicate with students via built-in messaging, and approve or deny requests dynamically. Features bulk approval/denial capabilities.
- **Master Admin Portal**: A high-level management dashboard for overseeing the entire system, adding new departments, managing user roles, and identifying clearance bottlenecks.

## Technologies Used

- **Backend**: PHP 8+ (Vanilla PHP)
- **Database**: MySQL (PDO)
- **Frontend Structure**: HTML5
- **Styling**: Custom CSS design system using CSS Variables for theme consistency, ensuring a premium, sleek UI without heavy frameworks. Features modern aesthetics like glassmorphism and smooth micro-animations.
- **Interactivity**: Vanilla JavaScript (Fetch API) for dynamic AJAX modals and real-time form submissions without page reloads.
- **PDF Generation**: FPDF library for generating downloadable clearance certificates.
- **Icons**: Lucide Icons.

## Project Structure

- `/admin` - Master administrator dashboard and user management logic.
- `/dept` - Department administrator review queues, bulk actions, and AJAX modals.
- `/student` - Student application workflows, progress tracking, and certificate generation.
- `/auth` - Login, registration, and session initialization.
- `/includes` - Shared components (header, footer), database configuration, authentication helpers, and course dropdown options.
- `/api` - API endpoints for asynchronous JavaScript requests (e.g., messaging, notifications).
- `/assets/css` - Global design system and layout stylesheets.

## Key Features

- **AJAX-Powered Modals**: Instant, seamless review modals for admins to manage dues and chat with students without page reloads.
- **Role-Based Access Control (RBAC)**: Secure routing and session management strictly separating Students, Department Admins, and Master Admins.
- **Real-Time Messaging & Notifications**: Built-in chat system allowing students and admins to resolve discrepancies instantly, accompanied by a notification bell system.
- **Dynamic Profile Auto-Fill**: Students can save their academic details in their profile, which automatically populates future clearance applications to save time.
- **Dynamic Certificate Generation**: Automatically unlocks and generates a printable PDF certificate once all departments have approved the student's application.
- **Bulk Actions**: Department admins can quickly select multiple students and approve or deny them in bulk.

## Setup Instructions

1. **Prerequisites**: Install [XAMPP](https://www.apachefriends.org/) (or any similar LAMP/WAMP stack).
2. **Database Setup**: 
   - Open phpMyAdmin and create a database named `clearpath_db`.
   - Import the `database.sql` file to set up the initial schema.
3. **Application Configuration**:
   - Ensure the database connection details in `config.php` and `includes/db.php` match your local setup (default is usually `root` with no password).
4. **Seed Database**:
   - Run the `reset_db.php` script (via command line `php reset_db.php` or by visiting it in the browser) to truncate tables and populate default test accounts.

## Test Credentials

After running the database reset script, you can use the following accounts to test the application:

**Master Admin**
- Email: `admin@clearpath.edu`
- Password: `admin`

**Student**
- Email: `student@clearpath.edu`
- Password: `student`

**Department Admins**
- Library: `library@clearpath.edu` / `library`
- Hostel: `hostel@clearpath.edu` / `hostel`
- Finance: `finance@clearpath.edu` / `finance`
- Examination: `examination@clearpath.edu` / `examination`
- Sports: `sports@clearpath.edu` / `sports`
- Lab: `lab@clearpath.edu` / `lab`
