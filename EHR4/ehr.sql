CREATE DATABASE IF NOT EXISTS ehr;
USE ehr;

-- admin
CREATE TABLE IF NOT EXISTS admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Using a secure hashed password (this is the hash for 'admin123')
INSERT INTO admin (username, password) VALUES ('admin', '$2y$10$8zUkhufKGXOe.XeSvHTJu.w.BbIQhALOI0s.nCMy0e/HY2dUivzXO');

-- patients
CREATE TABLE IF NOT EXISTS patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fullname VARCHAR(150),
  dob DATE,
  gender VARCHAR(20),
  contact VARCHAR(50),
  address VARCHAR(255),
  history TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- medical_history
CREATE TABLE IF NOT EXISTS medical_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  condition_name VARCHAR(255),
  notes TEXT,
  date_recorded DATETIME,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- medications
CREATE TABLE IF NOT EXISTS medications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  medication VARCHAR(255),
  dose VARCHAR(100),
  start_date DATE,
  notes TEXT,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- vitals
CREATE TABLE IF NOT EXISTS vitals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  bp VARCHAR(50),
  hr VARCHAR(50),
  temp VARCHAR(50),
  height VARCHAR(50),
  weight VARCHAR(50),
  date_taken DATETIME,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- diagnostics
CREATE TABLE IF NOT EXISTS diagnostics (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  problem VARCHAR(255),
  diagnosis TEXT,
  date_diagnosed DATETIME,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- treatment_plans
CREATE TABLE IF NOT EXISTS treatment_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  plan TEXT,
  notes TEXT,
  date_planned DATETIME,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- lab_results
CREATE TABLE IF NOT EXISTS lab_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  test_name VARCHAR(255),
  test_result TEXT,
  date_taken DATETIME,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- progress_notes
CREATE TABLE IF NOT EXISTS progress_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  note TEXT,
  author VARCHAR(100),
  date_written DATETIME,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- audit_trail
CREATE TABLE IF NOT EXISTS audit_trail (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  username VARCHAR(50),
  action_type ENUM('INSERT', 'UPDATE', 'DELETE'),
  table_name VARCHAR(50),
  record_id INT,
  patient_id INT,
  old_values TEXT,
  new_values TEXT,
  action_date DATETIME DEFAULT CURRENT_TIMESTAMP,
  ip_address VARCHAR(45)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
