# Barangay Health Center Survey Management System

A web-based system for creating, answering, monitoring, and analyzing barangay health surveys. The system has separate portals for administrators and residents.

## Features

### Administrator

- Create, edit, activate, deactivate, and reactivate surveys
- Manage resident and administrator accounts
- View respondents and monitor survey participation
- View survey results through tables and charts
- Generate, print, and export reports

### Resident

- View and answer available surveys
- Submit one response per survey
- View submitted surveys and submission history
- View synchronized dashboard counts
- Update profile and change password

## Technologies

- PHP
- MySQL
- HTML, CSS, and JavaScript
- Chart.js
- XAMPP

## Installation

1. Copy the project folder to:

   `C:\xampp\htdocs\`

2. Start **Apache** and **MySQL** in XAMPP.

3. Open phpMyAdmin:

   `http://localhost/phpmyadmin`

4. Create the required database and import the included `.sql` file.

5. Check the database credentials in the project’s configuration file.

6. Open the system in a browser:

   `http://localhost/[project-folder]/`

## Test Accounts

### Administrator

- **Username:** admin
- **Password:** password123

### Resident

- **Household Number:** HH-0001
- **Password:** H12345678

- **Household Number:** HH-0002
- **Password:** HH-002

### Note

New members and password resets default to the resident's own account number as the password (e.g. account `HH-0003` gets default password `HH-0003`). Residents are forced to change it on first login. Change the test password after logging in.

## Project Members

- Shanna Louis T. Carreon
- Angelie Joan Reyes
- Raiza Joy Evangelista
- Precious Eunice De Leon

