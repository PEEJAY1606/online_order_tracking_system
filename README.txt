ORDERTRACK - UPGRADED PHP / MYSQL ORDER TRACKING SYSTEM

WHAT CHANGED
- Redesigned admin dashboard with a dark navy sidebar and blue header.
- Dashboard summary cards for total, pending, in-progress, and delivered orders.
- Updated order form and responsive order table.
- Search orders by order number, customer, email, product, or status.
- View order details in a modal; Edit and Delete still use the existing database.
- Dashboard analytics chart and order-status summary.
- Server-side search and status filters.
- Customer-facing visual tracking timeline and status update history.
- Export all orders as CSV (Excel-compatible).
- Admin activity logs and username/password settings.
- Updated login and public tracking page to match the dashboard design.
- Existing database tables and columns are preserved.

FILES
- admin.php: Admin dashboard and order CRUD.
- style.css: Shared responsive design.
- db.php: Database connection.
- functions.php: Session, escaping, CSRF, and admin access helpers.
- login.php / logout.php: Admin authentication.
- index.php: Public order tracking.
- database.sql: Table structure for a new local database.
- upgrade_features.sql: Additive migration for order status history and admin activity logs. Import this into your existing database once.
- online_order_tracking.sql: Export containing the existing admin record and tables.

RUN LOCALLY USING XAMPP
1. Extract the folder online_order_tracking_system into C:\xampp\htdocs\
2. Open XAMPP Control Panel and start Apache and MySQL.
3. Open http://localhost/phpmyadmin
4. Create a database named online_order_tracking.
5. Select that database and import database.sql if you are starting with an empty database.
   If you already have the orders and admins tables, do not import a second dump over them. Import upgrade_features.sql into the existing database to enable status history and activity logs.
6. Open db.php and use your local XAMPP credentials:
      $host = "localhost";
      $dbname = "online_order_tracking";
      $username = "root";
      $password = "";
7. Open http://localhost/online_order_tracking_system/login.php
8. Sign in using the admin account already present in your admins table.
   This project archive does not contain a recoverable plain-text password. If you do not know it,
   reset the admin password by generating a new password_hash with PHP and updating that row.
9. After login, add a test order, then use the public tracking page to search its order number.

NEW FEATURE UPGRADE
1. Open phpMyAdmin and select your existing online_order_tracking database.
2. Choose Import and upload upgrade_features.sql.
3. The script creates order_status_history and activity_logs and adds an initial history entry for existing orders. It does not drop or replace your existing orders/admins tables.
4. Refresh the admin dashboard.

DATABASE COMPATIBILITY
The upgraded dashboard uses the same orders columns:
id, order_number, customer_name, customer_email, product_name, quantity,
order_date, status, tracking_note, updated_at.
It also keeps the admins table with id, username, and password_hash.
Do not rename these columns unless you also update the PHP queries.

INFINITYFREE
For hosting, keep using the exact database hostname, database name, username, and password
shown in your InfinityFree Control Panel. Do not use localhost/root on the live server.
Upload all project files, including style.css, into htdocs (or the project subfolder you use).
If the project is in htdocs/online_order_tracking_system, open:
https://YOUR-DOMAIN/online_order_tracking_system/
If you upload the contents directly into htdocs, open:
https://YOUR-DOMAIN/
Keep db.php credentials private. Never publish real database passwords.

NOTES
- Bootstrap is loaded from jsDelivr CDN, so the browser needs internet access for Bootstrap styling.
- Change any default or shared admin password.
- Delete any temporary setup script after using it; do not leave password-reset scripts publicly accessible.

FEATURE NOTES
- The CSV export can be opened in Microsoft Excel.
- Status history records updates made after the migration; the migration adds one baseline status record for each existing order.
- Email sending is not enabled by default because it requires SMTP credentials and hosting-specific configuration.
- For security, use a strong admin password and keep db.php credentials private.

SEPARATE ADMIN SECTIONS
-----------------------
The admin sidebar now opens one section at a time:
- Dashboard: admin.php?page=dashboard
- Add Order: admin.php?page=add
- All Orders: admin.php?page=orders
The sidebar stays visible and highlights the current section. Editing an order
opens the Add Order section with the selected order loaded into the form.

CUSTOMER MANAGEMENT (NEW)
1. Back up your project and database first.
2. In XAMPP, open http://localhost/phpmyadmin/ and select online_order_tracking.
3. Choose Import and import customer_management.sql.
4. The migration creates the customers table and copies existing customer names/emails from orders.
5. Replace your project files with this updated project, keeping your own db.php credentials if they differ.
6. Log in and choose Customers in the sidebar (admin.php?page=customers).

Customer Management includes adding/editing customer profiles, searching by name/email/phone,
viewing order history, and deleting profiles only when no orders use that email. New orders
also create/update the matching customer profile automatically. Existing orders are preserved.

For InfinityFree, import customer_management.sql into the hosted database and use the hosted
database credentials in db.php. Do not share db.php or database passwords publicly.


QR CODE TRACKING (NEW)
---------------------
- In Admin > All Orders, open an order using the View button.
- The Order Details window generates a QR code for that order's public tracking page.
- Use Download QR (PNG) to save a printable QR image, or Open Tracking Page to test it.
- Scanning the QR code opens index.php?order=ORDER_NUMBER and automatically loads that order.
- No database migration or schema changes are required.
- QR generation uses the qrcodejs library from cdnjs, so the admin browser needs internet access when generating/downloading QR codes.
- QR codes contain the tracking page URL and order number. Share them only with the intended customer.
