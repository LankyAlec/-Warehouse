-- Migration 001: housekeeping, bookings, and guests core tables
-- Nota: adattata per database esistenti con tabelle legacy (es. housekeeping_tasks con colonne room_id/task_type/status).
CREATE TABLE IF NOT EXISTS guests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(255) NOT NULL,
  contact_info TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS guest_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  guest_id INT NOT NULL,
  document_type VARCHAR(100) NOT NULL,
  document_number VARCHAR(100) NOT NULL,
  valid_until DATE DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_guest_documents_guest FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,
  UNIQUE KEY uq_guest_document (guest_id, document_type, document_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS room_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_room_types_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rooms (
  id INT AUTO_INCREMENT PRIMARY KEY,
  camera_id INT NOT NULL,
  room_type_id INT DEFAULT NULL,
  label VARCHAR(150) DEFAULT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'available',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_rooms_camera (camera_id),
  KEY idx_rooms_camera (camera_id),
  KEY idx_rooms_room_type (room_type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  guest_id INT NOT NULL,
  room_id INT NOT NULL,
  check_in DATE NOT NULL,
  check_out DATE NOT NULL,
  status VARCHAR(50) NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_bookings_guest FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE,
  CONSTRAINT fk_bookings_room FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
  KEY idx_bookings_guest (guest_id),
  KEY idx_bookings_room (room_id),
  KEY idx_bookings_dates (check_in, check_out)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_id INT NOT NULL,
  method VARCHAR(50) NOT NULL,
  amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status VARCHAR(50) NOT NULL DEFAULT 'pending',
  paid_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  KEY idx_payments_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Aggiorna la tabella housekeeping_tasks se presente con schema legacy (room_id/task_type/status).
DROP TABLE IF EXISTS housekeeping_tasks;

CREATE TABLE housekeeping_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  camera_id INT NOT NULL,
  soggiorno_id INT NULL,
  data_riferimento DATE NOT NULL,
  stato VARCHAR(32) NOT NULL,
  note TEXT NULL,
  source VARCHAR(32) NOT NULL DEFAULT 'manuale',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_camera_data (camera_id, data_riferimento),
  KEY idx_housekeeping_camera (camera_id),
  KEY idx_housekeeping_soggiorno (soggiorno_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
