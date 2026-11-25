# Ticketdesk

A local event and ticket booking application built with PHP 8.2, MySQL 8, Apache, and Bootstrap 5. Prices are in Sri Lankan rupees and event times use Asia/Colombo. The six sample events are fictional. My tickets shows personal bookings and a separate preview of other events.

## Install on another Windows laptop

1. Install XAMPP with PHP 8.2 at `C:/xampp`.
2. Install MySQL Community Server 8.0 at `C:/Program Files/MySQL/MySQL Server 8.0`.
3. Install Composer using `C:/xampp/php/php.exe`, and install Git for Windows.
4. Open a new PowerShell window and run these commands.

```powershell
git clone https://github.com/teamcore38-droid/Event-booking-system.git
cd Event-booking-system
$env:PATH = "C:\xampp\php;$env:PATH"
composer install
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/start.ps1
```

Open http://127.0.0.1:8186/. The launcher supports Windows 10/11 and expects the installation paths above. It creates its own MySQL instance on port 3318 and Apache on port 8186. Both ports must be available. Starting servers through the XAMPP control panel is unnecessary.

Internet is needed for cloning and Composer installation. Browser scripts, fonts, styles and sample images are bundled locally. Node.js and npm are optional for running the application. Other operating systems and custom installation paths require adapting the launcher.

## Sample data and accounts

First startup creates six fictional events with dates relative to the installation date, ticket categories, prices and capacities. They are After Hours Live, Design Assembly, The Print Studio, Courtyard Jazz Sessions, Colour & Ink Lab, and City in Focus. Subsequent starts preserve records. Edit dates in Manage if sample events eventually pass.

Open `var/local-accounts.txt` for randomly generated passwords. Administrator email is `admin@ticketdesk.local` and door-staff email is `staff@ticketdesk.local`. Participants register through the website. Fresh installations begin with empty bookings and attendance. Follow the complete demonstration below to populate reports.

## Start on subsequent occasions

Double-click **Start Ticketdesk.cmd** in the project folder. It starts the dedicated MySQL and Apache instances, keeps existing data, and opens http://127.0.0.1:8186/. The launcher can be used after a restart. The project uses the installed XAMPP Apache and PHP executables and the installed MySQL Server 8 executable. Its database files are in `var/mysql` and its database port is 3318. The Apache document root is `public`.

Open `var/local-accounts.txt` on this laptop for the generated admin and door-staff sign-ins. Participants register through the website. Keep the contents of `var` private. Copying the source without this folder creates a fresh database and new credentials on first startup. Runtime dependencies are installed on this laptop. To restore PHP dependencies from the lockfile, run `composer install` in the project folder.

## Try the complete flow

1. Sign in as admin. Open **Manage → Create event**. Choose a built-in cover or upload a JPEG, PNG or WebP image (up to 8 MB and 24 megapixels). The uploaded photo appears on event cards and tickets. You can replace or remove it when editing the event. Save the event, then add a category with a price, capacity, and optional numbered seats. Events with an upcoming start time and at least one category appear in Explore.
2. In another browser profile, register a participant. Select an event, choose a category, and book one ticket. Select a seat for numbered categories. Download the PDF or retrieve it later from **My tickets**.
3. For a paid ticket, receive payment separately, then record it with **Reports → Mark paid**. The system records when the admin marked the booking paid. Free tickets are cleared automatically. The price at booking time stays on the payment record if category prices change.
4. Sign in as staff. Open **Check-in**, select the event, and scan the QR using a camera attached to this laptop or enter its printed code. Confirm admission. The first successful admission records attendance. Repeat scans, tickets for a different event, and unpaid tickets are rejected.
5. Under **Reports**, the admin can filter by event, view amounts and attendance, and export the booking, payment and check-in details to PDF or Excel.

## Local operation

The server listens on this laptop only. A phone can display a downloaded ticket to the laptop camera. Scanning from a separate phone browser requires deploying the application to an HTTPS address reachable by that phone. A live payment gateway is outside this version's scope.

For a database backup, use MySQL Workbench to connect to 127.0.0.1 port 3318 as ticketdesk using the password field in `var/database.json`. Use **Server → Data Export**, select the ticketdesk schema, and export structure and data to a self-contained SQL file. Keep a copy outside the project folder. Use **Data Import** to restore into a fresh database.

## Verification

For the integration test, install Python 3.12 and run `python -m pip install requests Pillow numpy opencv-python PyMuPDF`. Ticketdesk must be running. The test creates temporary users and an event and removes them on success.

Run `vendor\bin\phpunit tests\AccessTest.php` for the permission and form-token tests. With Ticketdesk running, `python tests\verify.py` exercises registration, event and category creation, two simultaneous requests for the last seat, booking retries, ticket ownership, payment recording, check-in, duplicate rejection and PDF/XLSX export. It decodes the QR from the rendered PDF. The test uses the installed Python requests, NumPy, OpenCV and PyMuPDF packages and clears its own records on success.

## Troubleshooting and transfer

- Missing `vendor/autoload.php` means Composer dependencies need installation. Run `composer install`.
- If Composer is unrecognized, reopen PowerShell after installation.
- PHP needs PDO MySQL, mbstring, DOM/XML, fileinfo, OpenSSL and ZIP. Composer checks package requirements.
- Image uploads use GD. XAMPP provides it as an optional extension, loaded by the application's image worker. If encoding fails, enable `extension=gd` in `C:/xampp/php/php.ini` and restart the project servers.
- For Apache startup failures, inspect `var/apache.log` and port 8186.
- For database startup failures, inspect the `.err` file under `var/mysql` and port 3318.
- Camera scanning requires browser camera permission. Manual ticket-code entry is available.
- Keep `var` to preserve local records. To transfer existing data, export all `ticketdesk` tables with Workbench, including `event_covers`, and import them on the destination after initial setup. Restored accounts retain their original passwords. Securely retain the original account details because the destination account file initially describes its freshly generated accounts.
- A fresh Git clone creates new sample data and accounts. Local database files, passwords and generated screenshots are excluded from Git.

## Source layout

- `public/index.php` handles browser routes and participant views.
- `app/actions.php` handles form actions, access checks and database transactions.
- `app/management.php` contains administration and door-staff screens.
- `app/schema.sql` defines the relational schema. `app/reports.php` handles ticket and report downloads.
- `scripts/install.php` initializes tables, local operator accounts and example events.
- `public/assets` contains local styles, scanner code and event photography. Bootstrap, html5-qrcode, and the event images are served locally. Sample photographs are from Unsplash.
