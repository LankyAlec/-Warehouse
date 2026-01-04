-- Schedulazioni attivazione/disattivazione per edifici, piani e camere
CREATE TABLE IF NOT EXISTS struttura_schedules (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('edificio','piano','camera') NOT NULL,
  ref_id INT NOT NULL,
  stato TINYINT(1) NOT NULL DEFAULT 1,
  start_date DATE NOT NULL,
  end_date DATE DEFAULT NULL,
  restore_state TINYINT(1) NOT NULL DEFAULT 1,
  cascade_mode ENUM('off_only','always') NOT NULL DEFAULT 'off_only',
  applied_start TINYINT(1) NOT NULL DEFAULT 0,
  applied_end TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_sched_tipo_ref (tipo, ref_id),
  KEY idx_sched_start (start_date),
  KEY idx_sched_end (end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
