-- McRelish database (MySQL 5.7+ / MariaDB 10.4+, the versions shipped with XAMPP).
-- Money is stored as whole naira. All timestamps are stored in UTC.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id CHAR(36) NOT NULL PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NULL COMMENT 'NULL for accounts created with Google only',
  full_name VARCHAR(160) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  google_sub VARCHAR(64) NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
  token_hash CHAR(64) NOT NULL PRIMARY KEY COMMENT 'sha256 of the token held by the browser',
  user_id CHAR(36) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sessions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_sessions_user (user_id),
  INDEX idx_sessions_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  token_hash CHAR(64) NOT NULL PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_resets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  bucket CHAR(64) NOT NULL COMMENT 'sha256 of email + IP',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempts (bucket, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  role ENUM('admin','staff','dispatcher') NOT NULL,
  UNIQUE KEY uq_user_role (user_id, role),
  CONSTRAINT fk_roles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_sections (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  subtitle VARCHAR(300) NOT NULL DEFAULT '',
  image_path VARCHAR(120) NOT NULL DEFAULT '',
  sort_order INT NOT NULL DEFAULT 0,
  visible TINYINT(1) NOT NULL DEFAULT 1,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_items (
  id VARCHAR(64) NOT NULL PRIMARY KEY,
  section_id VARCHAR(64) NOT NULL,
  name VARCHAR(100) NOT NULL,
  description TEXT NOT NULL,
  image_path VARCHAR(120) NOT NULL DEFAULT '',
  price INT UNSIGNED NOT NULL DEFAULT 0,
  available TINYINT(1) NOT NULL DEFAULT 1,
  visible TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  config LONGTEXT NULL COMMENT 'JSON: meals, extras and option groups the server validates orders against',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_items_section FOREIGN KEY (section_id) REFERENCES menu_sections(id),
  INDEX idx_items_section (section_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keys: p:<item>, m:<item>:<meal>, e:<item>:<extra>, s:<item>:<extra> (price when ordered on its own).
CREATE TABLE IF NOT EXISTS menu_prices (
  pkey VARCHAR(160) NOT NULL PRIMARY KEY,
  product_name VARCHAR(160) NOT NULL,
  label VARCHAR(160) NOT NULL,
  price INT UNSIGNED NOT NULL,
  available TINYINT(1) NOT NULL DEFAULT 1,
  sort INT NOT NULL DEFAULT 0,
  low_stock TINYINT(1) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS delivery_zones (
  id CHAR(36) NOT NULL PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  fee INT UNSIGNED NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS store_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  store_open TINYINT(1) NOT NULL DEFAULT 1,
  note VARCHAR(500) NOT NULL DEFAULT '',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hero_promo (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
  label VARCHAR(120) NOT NULL DEFAULT '',
  title VARCHAR(160) NOT NULL DEFAULT '',
  subtitle VARCHAR(400) NOT NULL DEFAULT '',
  product_id VARCHAR(64) NOT NULL DEFAULT '',
  active TINYINT(1) NOT NULL DEFAULT 1,
  image_url VARCHAR(120) NOT NULL DEFAULT '' COMMENT 'file name inside public/uploads/promo; empty = bundled sample photo',
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS orders (
  id CHAR(36) NOT NULL PRIMARY KEY,
  reference VARCHAR(60) NOT NULL UNIQUE,
  access_token CHAR(36) NOT NULL,
  user_id CHAR(36) NULL,
  customer_name VARCHAR(100) NOT NULL,
  customer_phone VARCHAR(40) NOT NULL,
  customer_email VARCHAR(200) NOT NULL,
  method ENUM('pickup','delivery') NOT NULL,
  hub VARCHAR(80) NULL,
  zone_name VARCHAR(120) NULL,
  address VARCHAR(400) NULL,
  preferred_at VARCHAR(40) NOT NULL,
  notes VARCHAR(500) NULL,
  items LONGTEXT NOT NULL COMMENT 'JSON: priced order lines',
  subtotal INT UNSIGNED NOT NULL,
  delivery_fee INT UNSIGNED NOT NULL DEFAULT 0,
  total INT UNSIGNED NOT NULL,
  payment_status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  status ENUM('new','preparing','ready','completed','cancelled') NOT NULL DEFAULT 'new',
  tx_ref VARCHAR(100) NOT NULL UNIQUE,
  flw_transaction_id VARCHAR(40) NULL,
  paid_at DATETIME NULL,
  created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_orders_created (created_at),
  INDEX idx_orders_user (user_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id CHAR(36) NOT NULL PRIMARY KEY,
  actor_id CHAR(36) NULL,
  actor_email VARCHAR(190) NOT NULL DEFAULT '',
  action VARCHAR(80) NOT NULL,
  details LONGTEXT NOT NULL,
  created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
