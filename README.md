# ClearPath

**A digital university clearance system.** Students apply for clearance and upload documents; each department reviews, messages and approves; and a PDF clearance certificate unlocks when every department has signed off. It replaces the paper form that has to be carried from office to office.

Built with PHP and MySQL, without a framework, as a database-course project.

## Who uses it

| Role | What they do |
|---|---|
| **Student** | Fill in a profile once (course, batch), apply, upload supporting documents (PDF, JPG, PNG up to 5 MB), watch each department's decision in real time, chat with reviewers, and download the certificate |
| **Department admin** | Review a queue for their department (Library, Hostel, Finance, Examination, Sports or Lab), record dues, message students, approve or deny one application or many at once |
| **Master admin** | Add departments, assign roles, and see which departments are holding applications up |

## Features

- **Per-department approval tracking** with a status for every department on every application
- **Messaging and a notification bell** between students and reviewers, so a problem is settled inside the application
- **Bulk approve or deny** for department queues
- **Certificate generation:** a printable PDF (FPDF) appears only once every department has approved. [Sample certificate](Demo-Clearance-Certificate.pdf)
- **Role-based access control** on every page and endpoint
- **An audit log** of actions, and a profile that pre-fills future applications

## Tech stack

PHP 8 with PDO (prepared statements) · MySQL · vanilla JavaScript with `fetch` for modals and messaging · FPDF · Lucide icons · a hand-written CSS design system with CSS variables.

## Run it locally

You need a PHP and MySQL stack such as [XAMPP](https://www.apachefriends.org/).

1. Start **Apache** and **MySQL**.
2. In phpMyAdmin, create a database named `clearpath_db` and import [`database.sql`](database.sql). It creates the tables and the six departments.
3. Put this folder in `htdocs/clearpath` and check [`config.php`](config.php) (XAMPP's default is user `root`, empty password).
4. Open `http://localhost/clearpath/` and register. **New accounts are students.**
5. To try the admin side, promote an account in phpMyAdmin by setting `role` to `master_admin` (or `dept_admin` with a `department_id`) in the `users` table.

There are no seeded accounts and no default passwords.

## Project structure

```text
auth/       login, sign-up, sessions
student/    applications, progress tracking, certificate
dept/       department queues, bulk actions, review modals
admin/      master dashboard, departments and roles
api/        JSON endpoints for messaging and notifications
includes/   shared header and footer, database connection, auth helpers
assets/     stylesheets
uploads/    uploaded documents (never committed; scripts are blocked here)
```

## Security notes

- Uploads are checked by their real file content, stored under random names with a fixed extension, and `uploads/.htaccess` refuses to run scripts.
- Passwords use `password_hash` / `password_verify`; queries use prepared statements.
- Known gaps for a production deployment: no CSRF tokens yet and no session-ID regeneration on login.

## Author

Ahmad Maruf Hossain ([@SpicyFalcon619](https://github.com/SpicyFalcon619)), United International University.
