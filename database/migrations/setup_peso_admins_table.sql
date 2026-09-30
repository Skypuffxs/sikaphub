-- =====================================================================
-- SQL Query to Create the 2 Official PESO Admin Accounts
-- Database: u423765241_sikaphub_v2
-- Run this directly in phpMyAdmin under the SQL tab
-- =====================================================================

-- 1. Ensure user accounts exist in 'users' table
INSERT INTO users (email, email_verified_at, role, account_status)
VALUES ('pesoguimba@gmail.com', NOW(), 'admin', 'Active')
ON DUPLICATE KEY UPDATE role = 'admin', account_status = 'Active';

INSERT INTO users (email, email_verified_at, role, account_status)
VALUES ('sikaphub@gmail.com', NOW(), 'admin', 'Active')
ON DUPLICATE KEY UPDATE role = 'admin', account_status = 'Active';

-- 2. Insert Account 1: pesoguimba@gmail.com / pesoguimba / pesoguimba1$
SET @uid1 = (SELECT user_id FROM users WHERE email = 'pesoguimba@gmail.com' LIMIT 1);

INSERT INTO peso_admins (user_id, username, password_hash, admin_name, access_level)
VALUES (@uid1, 'pesoguimba', '$2y$10$sty7CeYFQX0LaR3nYhuMf.dduBmDqJDrdzIMXIFyRDo9ug7AE/5BK', 'PESO Guimba Administrator', 'SuperAdmin')
ON DUPLICATE KEY UPDATE username = 'pesoguimba', password_hash = '$2y$10$sty7CeYFQX0LaR3nYhuMf.dduBmDqJDrdzIMXIFyRDo9ug7AE/5BK', admin_name = 'PESO Guimba Administrator';

-- 3. Insert Account 2: sikaphub@gmail.com / sikaphub / sikaphub1$
SET @uid2 = (SELECT user_id FROM users WHERE email = 'sikaphub@gmail.com' LIMIT 1);

INSERT INTO peso_admins (user_id, username, password_hash, admin_name, access_level)
VALUES (@uid2, 'sikaphub', '$2y$10$Sh5B/DrXmRdsLI1w3pbQueDrL7c.ijPmr3kPGMr1sfLzRnMZCtdoq', 'SikapHub Administrator', 'SuperAdmin')
ON DUPLICATE KEY UPDATE username = 'sikaphub', password_hash = '$2y$10$Sh5B/DrXmRdsLI1w3pbQueDrL7c.ijPmr3kPGMr1sfLzRnMZCtdoq', admin_name = 'SikapHub Administrator';
