# ClearPath

ClearPath is a comprehensive, full-stack University Clearance System designed to streamline the graduation and departure process for students. It provides a seamless, dynamic experience for students, department administrators, and master administrators.

## Overview

The system eliminates paper-based clearance forms by digitizing the entire workflow. Students can track their clearance status in real-time, while department admins can efficiently manage queues, assign dues, and communicate directly with students.

It features three distinct user portals:
- **Student Portal**: Allows students to submit clearance applications, upload supporting documents, track departmental approvals in real-time, and download their final Clearance Certificate.
- **Department Admin Portal**: A streamlined dashboard for department-specific admins (e.g., Library, Finance, Labs) to review applications, communicate with students via built-in messaging, and approve or deny requests dynamically.
- **Master Admin Portal**: A high-level management dashboard for overseeing the entire system, adding new departments, managing user roles, and identifying clearance bottlenecks.

## Technologies Used

- **Backend**: PHP 8+ (Vanilla PHP)
- **Database**: MySQL (PDO)
- **Frontend Structure**: HTML5
- **Styling**: Custom CSS design system using CSS Variables for theme consistency, ensuring a premium, sleek UI without heavy frameworks.
- **Interactivity**: Vanilla JavaScript (Fetch API) for dynamic AJAX modals and real-time form submissions without page reloads.
- **Icons**: Lucide Icons.

## Project Structure

- `/admin` - Master administrator dashboard and user management logic.
- `/dept` - Department administrator review queues and AJAX modals.
- `/student` - Student application workflows and certificate generation.
- `/auth` - Login, registration, and session initialization.
- `/includes` - Shared components (header, footer), database configuration, and authentication helpers.
- `/assets/css` - Global design system and layout stylesheets.

## Key Features

- **AJAX-Powered Modals**: Instant, seamless review modals for admins to manage dues and chat with students without page reloads.
- **Role-Based Access Control (RBAC)**: Secure routing and session management strictly separating Students, Department Admins, and Master Admins.
- **Real-Time Messaging**: Built-in chat system allowing students and admins to resolve discrepancies instantly.
- **Dynamic Certificate Generation**: Automatically unlocks and generates a printable certificate once all departments have approved the student.
