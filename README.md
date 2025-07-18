CrimeAlert - Real-Time Crime Reporting System
CrimeAlert is a full-featured, web-based application designed to empower communities by enabling real-time crime reporting and safety awareness. Users can report incidents, view them on an interactive map, receive location-based alerts, and communicate with other members of the community.

✨ Key Features
👮 Crime Reporting & Management
Detailed Crime Reports: Users can submit reports with a title, description, crime type, and emergency level.

Geolocation: Pinpoint the exact location of an incident using an interactive Google Map or the user's current location.

Image Uploads: Attach multiple images to a crime report as evidence.

Anonymous Reporting: Option to submit reports anonymously to protect user privacy.

Report Dashboard: View a comprehensive list of all submitted reports with filtering and search capabilities.

Edit Reports: Users can edit the details of their own submitted reports.

🗺️ Interactive Mapping & Alerts
Live Crime Map: View all reported crimes on a full-screen, interactive map.

Map Filtering: Filter incidents on the map by crime type (Theft, Assault, etc.) and date range (e.g., Last 24 hours, Last 7 days).

Custom Alert Zones: Users can create personalized geographic zones (e.g., "Home," "Office") and receive email notifications for any crime reported within that radius.

Proximity Notifications: Automatically notifies nearby users via email when a new crime is reported.

👤 User & Community Interaction
User Authentication: Secure user registration and login system.

Profile Management: Users can update their personal information, change their password, and set a default location.

Community Members: View a list of all registered members in the community.

Real-Time Chat: Engage in one-on-one private messaging with other community members.

Personal Dashboard: A personalized dashboard greets users with stats on their reports (total, resolved, pending), a mini-map of recent incidents, and a chart of crime distribution.

🚀 Technologies Used
Backend: PHP, PDO for secure database operations.

Frontend: HTML5, Tailwind CSS, JavaScript (ES6+).

Database: MySQL (inferred).

APIs & Libraries:

Google Maps API: For all mapping features, location picking, and visualizations.

Chart.js: For visualizing crime data distribution on the dashboard.

📸 Screenshots
(You can add screenshots of your application here. Below are placeholder examples.)

Dashboard

Report a Crime

Live Crime Map

`

`

``

My Alert Zones

Members & Chat

Profile Page

`

`

``

🛠️ Setup and Installation
Follow these steps to get the project running on your local machine.

Prerequisites
A local server environment like XAMPP, WAMP, or MAMP.

PHP 7.4 or higher.

MySQL or MariaDB database.

A web browser.

A Google Maps API Key.

1. Clone the Repository
git clone https://github.com/your-username/your-repo-name.git
cd your-repo-name

2. Setup the Database
Start your Apache and MySQL services from your XAMPP/WAMP control panel.

Open a browser and navigate to http://localhost/phpmyadmin.

Create a new database. Let's name it crimealert_db.

Select the new database and go to the SQL tab.

Execute the following SQL schema to create the necessary tables.

--
-- Table structure for table `users`
--
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `notify_email_comment` tinyint(1) NOT NULL DEFAULT 1,
  `notify_email_status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `crime_reports`
--
CREATE TABLE `crime_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `crime_type` varchar(50) NOT NULL,
  `emergency_level` int(11) NOT NULL DEFAULT 3,
  `status` enum('pending','under_investigation','resolved') NOT NULL DEFAULT 'pending',
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `crime_reports_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `crime_images`
--
CREATE TABLE `crime_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `crime_id` int(11) NOT NULL,
  `image_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `crime_id` (`crime_id`),
  CONSTRAINT `crime_images_ibfk_1` FOREIGN KEY (`crime_id`) REFERENCES `crime_reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `alert_zones`
--
CREATE TABLE `alert_zones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `zone_name` varchar(100) NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `radius_km` decimal(5,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `alert_zones_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Table structure for table `messages`
--
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_text` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sender_id` (`sender_id`),
  KEY `receiver_id` (`receiver_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

3. Configure the Application
Navigate to the includes directory.

Create a file named config.php and add the following code. Replace the placeholder values with your actual database credentials.

<?php
// --- Database Configuration ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Your database password, often empty in XAMPP
define('DB_NAME', 'crimealert_db');

// --- Site Configuration ---
define('SITE_URL', 'http://localhost/your-repo-name'); // Base URL of your site
define('SESSION_NAME', 'crimealert_session');

// --- Error Reporting ---
error_reporting(E_ALL);
ini_set('display_errors', 1); // Set to 0 in production

// --- Start Session ---
session_name(SESSION_NAME);
session_start();
?>

Add Google Maps API Key: The API key is hardcoded in several files. You need to replace the placeholder AIzaSyDKxtYZuM7mDLDWULANqwI8kuChg4V_n7M with your own key in the following files:

report.php

dashboard.php

my-zones.php

map.php

profile.php

Recommendation: For better practice, define your API key in config.php and use it across the application.

4. Run the Application
Move the entire project folder into your server's root directory (e.g., C:/xampp/htdocs/).

Open your browser and navigate to http://localhost/your-repo-name/.

You should see the homepage. You can now register a new user and start exploring the features.

🤝 Contributing
Contributions are welcome! If you have suggestions for improvement or want to fix a bug, please feel free to:

Fork the repository.

Create a new feature branch (git checkout -b feature/AmazingFeature).

Commit your changes (git commit -m 'Add some AmazingFeature').

Push to the branch (git push origin feature/AmazingFeature).

Open a Pull Request.

📜 License
This project is licensed under the MIT License. See the LICENSE file for details.
