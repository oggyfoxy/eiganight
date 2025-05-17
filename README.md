
# eiganights 🎬🌙

eiganights is a collaborative movie annotation platform. Discover films, discuss specific scenes, create annotations, and share with a community of movie enthusiasts.

## Features (Current MVP)

*   User registration and login
*   Movie browsing and details (via TMDB API)
*   Watchlist management
*   User profiles with customizable visibility
*   Friendship system (send, accept, decline, unfriend)
*   Movie rating and commenting
*   Admin panel:
    *   User management (ban/unban)
    *   FAQ and Terms content management
*   Scene annotation system:
    *   Users can create discussion threads tied to specific movies and scenes (identified by timecode/description).
    *   Annotations are visible on movie detail pages and user profiles.
*   Basic forum for discussions.
*   Static pages: FAQ, Terms & Conditions, Contact Us.

## Tech Stack

*   **Backend:** PHP
*   **Database:** MySQL
*   **Frontend:** HTML, CSS, Vanilla JavaScript (for TMDB search, etc.)
*   **API:** The Movie Database (TMDB) API for movie data.

## Getting Started (Contribution Guide)

Welcome, contributor! Here's how to get eiganights running locally.

### Prerequisites

1.  **Web Server:** A local web server environment like XAMPP, MAMP, WAMP, or a manual Apache/Nginx + PHP setup.
    *   **PHP:** Version 7.4 or newer recommended (project uses `password_hash`, `mysqli`).
    *   **MySQL:** Version 5.7 or newer (or MariaDB equivalent).
2.  **TMDB API Key:** You'll need your own API key from [The Movie Database (TMDB)](https://www.themoviedb.org/settings/api). It's free to register.
3.  **Git:** For version control.

### Setup Instructions

#### 1. Clone the Repository

```bash
git clone <repository-url>
cd eiganights
```
*(Replace `<repository-url>` with the actual URL of your Git repository)*

#### 2. Database Setup

1.  **Create Database:**
    Using your MySQL admin tool (phpMyAdmin, command line, etc.), create a new database. The default name used in the project is `eiganights`.
    ```sql
    CREATE DATABASE eiganights CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
    ```

2.  **Create Database User (Recommended):**
    For better security than using `root`.
    ```sql
    -- Example for creating a user 'eigaapp' with password 'yoursecurepassword'
    CREATE USER 'eigaapp'@'localhost' IDENTIFIED BY 'yoursecurepassword';
    GRANT ALL PRIVILEGES ON eiganights.* TO 'eigaapp'@'localhost';
    FLUSH PRIVILEGES;
    ```
    *Adjust `'yoursecurepassword'` and `'eigaapp'` as needed.*

3.  **Import Schema:**
    Import the `db/setup.sql` file into your newly created `eiganights` database. This will create all the necessary tables.
    *   **Using command line:**
        ```bash
        mysql -u your_db_user -p eiganights < db/setup.sql
        ```
        (Replace `your_db_user` with `eigaapp` or `root` if you skipped user creation).
    *   **Using phpMyAdmin:** Select your `eiganights` database, go to the "Import" tab, and upload `db/setup.sql`.

#### 3. Configure `config.php`

1.  In the project root (`eiganights/`), find the `config.php.template` file.
2.  **Copy and rename it** to `config.php`.
    *   On macOS/Linux:
        ```bash
        cp config.php.template config.php
        ```
    *   On Windows (Command Prompt):
        ```cmd
        copy config.php.template config.php
        ```
    *   On Windows (PowerShell):
        ```powershell
        Copy-Item config.php.template config.php
        ```

3.  **Edit `config.php`** with your local settings:
    *   `DB_USER`: Your MySQL username (e.g., `eigaapp` or `root`).
    *   `DB_PASS`: Your MySQL password (can be blank for default XAMPP/MAMP root).
    *   `DB_NAME`: Should be `eiganights` (or whatever you named it).
    *   `TMDB_API_KEY`: Paste your TMDB API key here.
    *   `BASE_URL` (script part):
        *   If you place the `eiganights` folder directly in your web server's root (e.g., `htdocs/eiganights` or `www/eiganights`), and you access it via `http://localhost/eiganights/`, then the auto-detected `$script = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');` should result in `'/eiganights'`.
        *   If you access it as `http://localhost/` (meaning `eiganights` contents are directly in `htdocs` or `www`), `$script` should be `''`.
        *   Adjust if necessary. For example, if your project is at `http://localhost/myprojects/eiganights/`, you might need to set `$script = '/myprojects/eiganights';` in your local `config.php`.

    **`config.php` is ignored by Git and should NOT be committed.**

#### 4. Set Up Admin Account (Optional, but useful)

If you want to access the admin panel:
1.  Create a temporary PHP file (e.g., `temp_create_admin.php` in the project root):
    ```php
    <?php
    // temp_create_admin.php
    echo password_hash('password', PASSWORD_DEFAULT);
    ?>
    ```
2.  Run it (e.g., open `http://localhost/eiganights/temp_create_admin.php` in your browser).
3.  Copy the generated hash (it will look something like `$2y$10$...`).
4.  Insert the admin user into your database using a MySQL tool:
    ```sql
    USE eiganights;
    INSERT INTO users (username, password, role) VALUES ('admin', 'PASTE_YOUR_HASHED_PASSWORD_HERE', 'admin');
    ```
5.  **Important: Delete the `temp_create_admin.php` file afterwards.**

#### 5. Access the Site

Open your web browser and navigate to your local development URL (e.g., `http://localhost/eiganights/` or `http://your-virtual-host-name/`).

### Development Environment Specifics

*   **Windows (XAMPP/WAMP):**
    *   Place the `eiganights` project folder inside your `htdocs` (XAMPP) or `www` (WAMP) directory.
    *   Start Apache and MySQL services from the XAMPP/WAMP control panel.
    *   Access phpMyAdmin via `http://localhost/phpmyadmin` for database management.
*   **macOS (MAMP or built-in Apache/PHP + Homebrew MySQL):**
    *   **MAMP:** Place the `eiganights` project folder inside MAMP's `htdocs` directory. Start servers from MAMP.
    *   **Built-in/Homebrew:** Configure Apache/Nginx virtual hosts to point to your project directory. Ensure PHP and MySQL (installed via Homebrew) are running.
*   **Linux (LAMP stack):**
    *   Place the `eiganights` project folder typically in `/var/www/html/eiganights` (or configure a virtual host to point elsewhere).
    *   Ensure Apache/Nginx, PHP, and MySQL services are running (e.g., `sudo systemctl start apache2 mysql phpX.Y-fpm`).
    *   Check file permissions if you encounter access issues. The web server user (e.g., `www-data`) might need write permissions for certain directories if you implement file uploads later.

### Contribution Guidelines (To Be Expanded)

*   Follow the existing coding style.
*   Create feature branches from `main` or `develop` (if a develop branch exists).
*   Write clear commit messages.
*   Ensure your changes work across different browsers (target modern browsers).
*   *(More guidelines can be added here: testing, pull request process, etc.)*

---

Happy annotating! 🍿
