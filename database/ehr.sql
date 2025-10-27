

CREATE DATABASE IF NOT EXISTS ehr;
USE ehr;

-- admin
CREATE TABLE IF NOT EXISTS admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  session_id VARCHAR(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Using a secure hashed password (this is the hash for 'admin123')
INSERT INTO admin (username, password) VALUES ('admin', '$2y$10$8zUkhufKGXOe.XeSvHTJu.w.BbIQhALOI0s.nCMy0e/HY2dUivzXO');

-- patients
CREATE TABLE IF NOT EXISTS patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  fullname VARCHAR(150),
  dob DATE,
  gender VARCHAR(20),
  age INT(2),
  contact VARCHAR(50),
  address VARCHAR(255),
  history TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- medical_history
CREATE TABLE IF NOT EXISTS medical_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  condition_name VARCHAR(255),
  status VARCHAR(50),
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
  route VARCHAR(100),
  indication VARCHAR(200),
  prescriber VARCHAR(100),
  status VARCHAR(100),
  patient_instructions TEXT,
  pharmacy_instructions TEXT,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- vitals
CREATE TABLE IF NOT EXISTS vitals (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  recorded_by VARCHAR(150),
  bp VARCHAR(50),
  respiratory_rate VARCHAR(50),
  hr VARCHAR(50),
  temp VARCHAR(50),
  height VARCHAR(50),
  weight VARCHAR(50),
  oxygen_saturation VARCHAR(50),
  pain_scale VARCHAR(50),
  date_taken DATETIME,
  general_appearance TEXT,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- diagnostics
CREATE TABLE IF NOT EXISTS diagnostics (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  study_type VARCHAR(255),
  body_part_region TEXT,
  study_description TEXT,
  clinical_indication VARCHAR(150),
  image_quality VARCHAR(150),
  order_by VARCHAR(150),
  performed_by VARCHAR(150),
  Interpreted_by VARCHAR(150),
  Imaging_facility VARCHAR(150),
  radiology_findings TEXT,
  impression_conclusion TEXT,
  recommendations TEXT,
  date_diagnosed DATETIME,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- treatment_plans
CREATE TABLE IF NOT EXISTS treatment_plans (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  plan TEXT,
  intervention TEXT,
  problems TEXT,
  frequency VARCHAR(150),
  duration VARCHAR(150),
  order_by VARCHAR(150),
  assigned_to VARCHAR(150),
  date_started DATE,
  date_ended DATE,
  special_instructions TEXT,
  patient_education_provided TEXT,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- lab_results
CREATE TABLE IF NOT EXISTS lab_results (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  test_name VARCHAR(255),
  test_category VARCHAR(255),
  test_code VARCHAR(255),
  test_result TEXT,
  result_status VARCHAR(150),
  units VARCHAR(150),
  reference_range VARCHAR(150),
  order_by VARCHAR(150),
  collected_by VARCHAR(150),
  laboratory_facility VARCHAR(150),
  clinical_interpretation VARCHAR(150),
  date_taken DATETIME,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- progress_notes
CREATE TABLE IF NOT EXISTS progress_notes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  note TEXT,
  focus TEXT,
  author VARCHAR(100),
  date_written DATETIME,
  FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- physical_assessments
CREATE TABLE IF NOT EXISTS physical_assessments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT,
  assessed_by VARCHAR(150),
  head_and_neck TEXT,
  cardiovascular TEXT,
  respiratory TEXT,
  abdominal TEXT,
  neurological TEXT,
  musculoskeletal TEXT,
  skin TEXT,
  psychiatric TEXT,
  date_assessed DATETIME,
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

-- Create surgeries table
CREATE TABLE IF NOT EXISTS `surgeries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `procedure_name` varchar(255) NOT NULL,
  `date_surgery` date DEFAULT NULL,
  `hospital` varchar(255) DEFAULT NULL,
  `surgeon` varchar(255) DEFAULT NULL,
  `complications` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create surgeries table
CREATE TABLE IF NOT EXISTS `surgeries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `procedure_name` varchar(255) NOT NULL,
  `date_surgery` date DEFAULT NULL,
  `hospital` varchar(255) DEFAULT NULL,
  `surgeon` varchar(255) DEFAULT NULL,
  `complications` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create allergies table
CREATE TABLE IF NOT EXISTS `allergies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `allergen` varchar(255) NOT NULL,
  `reaction` varchar(255) NOT NULL,
  `severity` enum('Mild','Moderate','Severe','Life-threatening') DEFAULT 'Mild',
  `date_identified` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create family_history table
CREATE TABLE IF NOT EXISTS `family_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `relationship` varchar(100) NOT NULL,
  `condition` varchar(255) NOT NULL,
  `age_at_diagnosis` varchar(50) DEFAULT NULL,
  `current_status` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create lifestyle_info table
CREATE TABLE IF NOT EXISTS `lifestyle_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `smoking_status` enum('Never','Former','Current') DEFAULT 'Never',
  `smoking_details` text DEFAULT NULL,
  `alcohol_use` enum('None','Occasional','Moderate','Heavy') DEFAULT 'None',
  `alcohol_details` text DEFAULT NULL,
  `exercise` varchar(255) DEFAULT NULL,
  `diet` varchar(255) DEFAULT NULL,
  `recreational_drug_use` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  FOREIGN KEY (`patient_id`) REFERENCES `patients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
