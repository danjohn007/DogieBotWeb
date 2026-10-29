-- DoggieBot CRM - segmentación de clientes + usuarios/permisos
-- Ejecutar UNA sola vez sobre crmcamar_doggiebot antes de subir la nueva web.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_customer_types (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(500) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO dg_customer_types(name,description,active,sort_order) VALUES
('Prospecto','Contacto captado por WhatsApp que todavía está en etapa de interés o consulta.',1,10),
('Cliente Pick&Go','Cliente que utiliza el servicio de pedido para recoger en sucursal.',1,20),
('Cliente Reservación','Cliente que acostumbra programar su visita mediante una reservación.',1,30),
('Cliente Evento / Corporativo','Contacto interesado en catering, eventos sociales o servicios empresariales.',1,40),
('Cliente Frecuente','Cliente recurrente que mantiene una relación continua con Los Dogos.',1,50)
ON DUPLICATE KEY UPDATE description=VALUES(description),sort_order=VALUES(sort_order);

SET @has_customer_type := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dg_contacts' AND COLUMN_NAME='customer_type_id');
SET @sql := IF(@has_customer_type=0,'ALTER TABLE dg_contacts ADD COLUMN customer_type_id BIGINT UNSIGNED NULL AFTER already_knew','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_customer_idx := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='dg_contacts' AND INDEX_NAME='idx_dg_contacts_customer_type');
SET @sql := IF(@has_customer_idx=0,'ALTER TABLE dg_contacts ADD INDEX idx_dg_contacts_customer_type (customer_type_id)','SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS dg_user_permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  module_key VARCHAR(50) NOT NULL,
  can_view TINYINT(1) NOT NULL DEFAULT 0,
  can_edit TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dg_user_module(user_id,module_key),
  CONSTRAINT fk_dg_permissions_user FOREIGN KEY(user_id) REFERENCES dg_admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
