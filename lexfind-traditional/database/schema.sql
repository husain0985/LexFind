-- ============================================================
-- LexFind - Legal Advisor Finder
-- Database schema + seed data
-- Import this file in phpMyAdmin, or run:
--   mysql -u root -p < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS lexfind_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lexfind_db;

-- ------------------------------------------------------------
-- USERS  (clients, advisors, admins all live here)
-- ------------------------------------------------------------
CREATE TABLE users (
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  first_name            VARCHAR(100) NOT NULL,
  last_name             VARCHAR(100) NOT NULL,
  email                 VARCHAR(150) NOT NULL UNIQUE,
  phone                 VARCHAR(30)  DEFAULT NULL,
  password_hash         VARCHAR(255) NOT NULL,
  role                  ENUM('client','advisor','admin') NOT NULL DEFAULT 'client',
  address               VARCHAR(255) DEFAULT NULL,
  status                ENUM('active','suspended') NOT NULL DEFAULT 'active',
  is_verified           TINYINT(1) NOT NULL DEFAULT 1,
  avatar                VARCHAR(255) DEFAULT NULL,
  verification_token    VARCHAR(64) DEFAULT NULL,
  verification_expires  DATETIME DEFAULT NULL,
  created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_verification_token (verification_token)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- ADVISOR PROFILES  (1-1 extension of users where role='advisor')
-- ------------------------------------------------------------
CREATE TABLE advisor_profiles (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  user_id           INT NOT NULL UNIQUE,
  specialty         VARCHAR(100) NOT NULL,
  bar_license       VARCHAR(100) DEFAULT NULL,
  bar_id_card       VARCHAR(255) DEFAULT NULL,   -- stored filename of uploaded Bar Council ID card
  years_exp         INT DEFAULT 0,
  fee               DECIMAL(10,2) DEFAULT 0,
  languages         VARCHAR(255) DEFAULT 'Bengali, English',
  bio               TEXT,
  tags              VARCHAR(255) DEFAULT NULL,   -- comma-separated keywords shown as pills on cards
  location          VARCHAR(150) DEFAULT 'Dhaka',
  session_duration  INT DEFAULT 60,       -- minutes
  buffer_minutes    INT DEFAULT 15,
  approval_status   ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  rating_avg        DECIMAL(3,2) DEFAULT 0,
  rating_count      INT DEFAULT 0,
  created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- AVAILABILITY  (weekly on/off toggle per advisor)
-- day_of_week: 0=Mon 1=Tue 2=Wed 3=Thu 4=Fri 5=Sat 6=Sun
-- ------------------------------------------------------------
CREATE TABLE advisor_availability (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  advisor_id   INT NOT NULL,
  day_of_week  TINYINT NOT NULL,
  is_active    TINYINT(1) DEFAULT 0,
  UNIQUE KEY uniq_advisor_day (advisor_id, day_of_week),
  FOREIGN KEY (advisor_id) REFERENCES advisor_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- BOOKINGS
-- ------------------------------------------------------------
CREATE TABLE bookings (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  client_id        INT NOT NULL,
  advisor_id       INT NOT NULL,
  booking_date     DATE NOT NULL,
  booking_time     VARCHAR(20) NOT NULL,
  meeting_type     ENUM('Video Call','Phone Call','In-Person') DEFAULT 'Video Call',
  case_description TEXT,
  whatsapp_number  VARCHAR(30) DEFAULT NULL,
  status           ENUM('pending','confirmed','completed','cancelled','rejected') NOT NULL DEFAULT 'pending',
  fee              DECIMAL(10,2) DEFAULT 0,
  payment_status   ENUM('unpaid','paid','refunded') NOT NULL DEFAULT 'unpaid',
  active_slot_date DATE GENERATED ALWAYS AS (
                      CASE WHEN status IN ('cancelled','rejected') THEN NULL ELSE booking_date END
                    ) VIRTUAL,
  created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (client_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (advisor_id) REFERENCES advisor_profiles(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_advisor_active_slot (advisor_id, active_slot_date, booking_time)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- PAYMENTS  (one row per successful/failed payment attempt on a booking)
-- Demo/simulated gateway for now — swap the logic in actions/process-payment.php
-- for a real provider (SSLCommerz/bKash/Stripe) later without changing this schema.
-- ------------------------------------------------------------
CREATE TABLE payments (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  booking_id      INT NOT NULL,
  amount          DECIMAL(10,2) NOT NULL,
  method          VARCHAR(50) NOT NULL DEFAULT 'demo',
  transaction_id  VARCHAR(100) NOT NULL,
  status          ENUM('success','failed') NOT NULL DEFAULT 'success',
  created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_transaction_id (transaction_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- REVIEWS
-- ------------------------------------------------------------
CREATE TABLE reviews (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  booking_id   INT NOT NULL UNIQUE,
  client_id    INT NOT NULL,
  advisor_id   INT NOT NULL,
  rating       TINYINT NOT NULL,
  review_text  TEXT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  FOREIGN KEY (client_id)  REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (advisor_id) REFERENCES advisor_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- LOGIN ATTEMPTS  (brute-force / rate-limit tracking)
-- ------------------------------------------------------------
CREATE TABLE login_attempts (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  email         VARCHAR(150) NOT NULL,
  ip_address    VARCHAR(45) NOT NULL,
  attempted_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email (email),
  KEY idx_ip (ip_address),
  KEY idx_attempted_at (attempted_at)
) ENGINE=InnoDB;
-- ------------------------------------------------------------
-- CONTACT MESSAGES  (from the public Contact page)
-- ------------------------------------------------------------
CREATE TABLE contact_messages (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150),
  email       VARCHAR(150),
  subject     VARCHAR(255),
  message     TEXT,
  is_read     TINYINT(1) NOT NULL DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- Password for every seeded account is:  Password123
-- (hash generated with PHP password_hash / bcrypt)
-- ============================================================
INSERT INTO users (first_name,last_name,email,phone,password_hash,role,address,status) VALUES
('Admin','User','admin@lexfind.com.bd','+880 1700 000000','$2y$10$e0NRHU5iM1i7Q0f1x1s1UOeYQ8b0Z0h2j7q0Z0h2j7q0Z0h2j7q0Z','admin','Dhaka, Bangladesh','active'),
('John','Doe','john.doe@email.com','+880 1700 000001','$2y$10$e0NRHU5iM1i7Q0f1x1s1UOeYQ8b0Z0h2j7q0Z0h2j7q0Z0h2j7q0Z','client','Dhaka, Bangladesh','active'),
('Rahim','Khan','rahim@law.com','+880 1711 000002','$2y$10$e0NRHU5iM1i7Q0f1x1s1UOeYQ8b0Z0h2j7q0Z0h2j7q0Z0h2j7q0Z','advisor','Dhaka, Bangladesh','active'),
('Sara','Ahmed','sara.ahmed@law.com','+880 1711 000003','$2y$10$e0NRHU5iM1i7Q0f1x1s1UOeYQ8b0Z0h2j7q0Z0h2j7q0Z0h2j7q0Z','advisor','Dhaka, Bangladesh','active'),
('Nisha','Patel','nisha@law.com','+880 1711 000004','$2y$10$e0NRHU5iM1i7Q0f1x1s1UOeYQ8b0Z0h2j7q0Z0h2j7q0Z0h2j7q0Z','advisor','Chittagong, Bangladesh','active'),
('Karim','Uddin','karim@law.com','+880 1711 000005','$2y$10$e0NRHU5iM1i7Q0f1x1s1UOeYQ8b0Z0h2j7q0Z0h2j7q0Z0h2j7q0Z','advisor','Dhaka, Bangladesh','active'),
('Ali','Hossain','ali@law.com','+880 1711 000006','$2y$10$e0NRHU5iM1i7Q0f1x1s1UOeYQ8b0Z0h2j7q0Z0h2j7q0Z0h2j7q0Z','advisor','Sylhet, Bangladesh','active'),
('Maya','Chowdhury','maya@law.com','+880 1711 000007','$2y$10$e0NRHU5iM1i7Q0f1x1s1UOeYQ8b0Z0h2j7q0Z0h2j7q0Z0h2j7q0Z','advisor','Dhaka, Bangladesh','active');

-- NOTE: the placeholder hashes above are replaced by setup.php on first run
-- (see setup.php / README) so login works out of the box. If you prefer to
-- generate them yourself, the plain-text password for every seeded user is
-- Password123.

INSERT INTO advisor_profiles (user_id,specialty,bar_license,years_exp,fee,languages,bio,tags,location,approval_status,rating_avg,rating_count) VALUES
(3,'Criminal','BAR-2013-0042',12,3000,'Bengali, English','Experienced criminal defence attorney with 12+ years of practice in Dhaka courts. Specialising in white-collar crime, bail hearings, and appellate work.','Criminal Defence,Appeals,Bail','Dhaka','approved',4.9,38),
(4,'Family','BAR-2016-0091',8,2500,'Bengali, English','Family law specialist helping clients navigate sensitive personal legal matters with care and professionalism.','Divorce,Child Custody,Inheritance','Dhaka','approved',4.8,51),
(5,'Corporate','BAR-2009-0017',15,5000,'Bengali, English, Hindi','Top corporate lawyer with extensive experience in mergers, acquisitions, and regulatory compliance for multinationals in Bangladesh.','M&A,Contracts,Compliance','Chittagong','approved',4.7,29),
(6,'Civil','BAR-2014-0063',10,2000,'Bengali, English','Civil litigator with a strong track record in property disputes and contractual matters at the High Court Division.','Property,Contracts,Disputes','Dhaka','approved',4.6,44),
(7,'Property','BAR-2018-0122',6,2200,'Bengali, English','Property law expert handling land registration, title disputes, and real estate transactions across Bangladesh.','Land Disputes,Registration,Deeds','Sylhet','approved',4.5,22),
(8,'Immigration','BAR-2015-0077',9,3500,'Bengali, English','Skilled immigration lawyer assisting clients with visa applications, residency permits, and overseas employment contracts.','Visa,Work Permits,Citizenship','Dhaka','approved',4.8,33);

-- default weekly availability (matches original demo design)
INSERT INTO advisor_availability (advisor_id, day_of_week, is_active) VALUES
(1,0,1),(1,1,1),(1,2,0),(1,3,1),(1,4,1),(1,5,0),(1,6,0),
(2,0,0),(2,1,1),(2,2,1),(2,3,0),(2,4,0),(2,5,1),(2,6,1),
(3,0,1),(3,1,1),(3,2,1),(3,3,1),(3,4,0),(3,5,0),(3,6,0),
(4,0,0),(4,1,1),(4,2,1),(4,3,0),(4,4,1),(4,5,1),(4,6,0),
(5,0,1),(5,1,0),(5,2,0),(5,3,1),(5,4,0),(5,5,1),(5,6,0),
(6,0,0),(6,1,0),(6,2,1),(6,3,0),(6,4,1),(6,5,0),(6,6,1);
