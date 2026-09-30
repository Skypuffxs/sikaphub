-- =====================================================================
-- PESO Admin Table Setup & Account Seeding Migration
-- Run in phpMyAdmin on Hostinger (u423765241_sikaphub_v2)
-- =====================================================================

-- 1. Create peso_admins table if missing
CREATE TABLE IF NOT EXISTS peso_admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) NULL UNIQUE,
    password_hash VARCHAR(255) NULL,
    admin_name VARCHAR(100) DEFAULT 'PESO Admin',
    access_level VARCHAR(50) DEFAULT 'SuperAdmin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Seed User Accounts in 'users' table
INSERT INTO users (email, email_verified_at, role, account_status)
VALUES ('pesoguimba@gmail.com', NOW(), 'admin', 'Active')
ON DUPLICATE KEY UPDATE role = 'admin', account_status = 'Active';

INSERT INTO users (email, email_verified_at, role, account_status)
VALUES ('sikaphub@gmail.com', NOW(), 'admin', 'Active')
ON DUPLICATE KEY UPDATE role = 'admin', account_status = 'Active';

-- 3. Seed Admin Credentials in 'peso_admins' table
-- Account 1: pesoguimba@gmail.com / pesoguimba1$
SET @uid1 = (SELECT user_id FROM users WHERE email = 'pesoguimba@gmail.com' LIMIT 1);

INSERT INTO peso_admins (user_id, username, password_hash, admin_name, access_level)
VALUES (@uid1, 'pesoguimba', '$2y$10$sty7CeYFQX0LaR3nYhuMf.dduBmDqJDrdzIMXIFyRDo9ug7AE/5BK', 'PESO Guimba Admin', 'SuperAdmin')
ON DUPLICATE KEY UPDATE username = 'pesoguimba', password_hash = '$2y$10$sty7CeYFQX0LaR3nYhuMf.dduBmDqJDrdzIMXIFyRDo9ug7AE/5BK';

-- Account 2: sikaphub@gmail.com / sikaphub1$
SET @uid2 = (SELECT user_id FROM users WHERE email = 'sikaphub@gmail.com' LIMIT 1);

INSERT INTO peso_admins (user_id, username, password_hash, admin_name, access_level)
VALUES (@uid2, 'sikaphub', '$2y$10$Sh5B/DrXmRdsLI1w3pbQueDrL7c.ijPmr3kPGMr1sfLzRnMZCtdoq', 'SikapHub Admin', 'SuperAdmin')
ON DUPLICATE KEY UPDATE username = 'sikaphub', password_hash = '$2y$10$Sh5B/DrXmRdsLI1w3pbQueDrL7c.ijPmr3kPGMr1sfLzRnMZCtdoq';
