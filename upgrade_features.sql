-- OrderTrack feature upgrade (safe additive migration)
-- Select your existing online_order_tracking database in phpMyAdmin before importing.

CREATE TABLE IF NOT EXISTS order_status_history (
  id INT NOT NULL AUTO_INCREMENT,
  order_id INT NOT NULL,
  status VARCHAR(40) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_by VARCHAR(50) DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_order_history (order_id, changed_at),
  CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS activity_logs (
  id INT NOT NULL AUTO_INCREMENT,
  admin_id INT DEFAULT NULL,
  admin_username VARCHAR(50) DEFAULT NULL,
  action VARCHAR(80) NOT NULL,
  details VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activity_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add a first history entry for existing orders, without duplicating entries.
INSERT INTO order_status_history (order_id, status, note, changed_at, changed_by)
SELECT o.id, o.status, COALESCE(o.tracking_note, 'Existing order status'), COALESCE(o.updated_at, o.order_date), 'System'
FROM orders o
WHERE NOT EXISTS (SELECT 1 FROM order_status_history h WHERE h.order_id = o.id);
