-- Customer Management migration. Select online_order_tracking in phpMyAdmin, then Import.
CREATE TABLE IF NOT EXISTS customers (
  id INT NOT NULL AUTO_INCREMENT,
  customer_name VARCHAR(100) NOT NULL,
  customer_email VARCHAR(120) NOT NULL,
  contact_number VARCHAR(30) DEFAULT NULL,
  address TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_email (customer_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Import existing customers from orders without duplicating email addresses.
INSERT INTO customers (customer_name, customer_email)
SELECT customer_name, LOWER(customer_email)
FROM orders
WHERE customer_email IS NOT NULL AND customer_email <> ''
GROUP BY LOWER(customer_email)
ON DUPLICATE KEY UPDATE customer_name=VALUES(customer_name);
