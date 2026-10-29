SET NAMES utf8mb4;SET time_zone = '+00:00';
CREATE TABLE IF NOT EXISTS dg_admin_users(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,email VARCHAR(190) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,full_name VARCHAR(190) NOT NULL,role ENUM('admin','staff') NOT NULL DEFAULT 'admin',profile_photo VARCHAR(255) NULL,active TINYINT(1) NOT NULL DEFAULT 1,last_login_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO dg_admin_users(email,password_hash,full_name,role,active) VALUES('admin@losdogos.com','$2y$12$en2Q7FO0q4W97WhDFKX7aef/NhToytIEVMwZ5pCfmTqWGiYyvPWhK','Administrador DoggieBot','admin',1) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name);
CREATE TABLE IF NOT EXISTS dg_contacts(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,wa_id VARCHAR(40) NOT NULL UNIQUE,phone VARCHAR(40) NOT NULL,profile_name VARCHAR(190) NULL,full_name VARCHAR(190) NULL,email VARCHAR(190) NULL,already_knew TINYINT(1) NULL,active TINYINT(1) NOT NULL DEFAULT 1,last_inbound_at DATETIME NULL,last_outbound_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(phone)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS dg_conversations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,contact_id BIGINT UNSIGNED NOT NULL UNIQUE,current_step VARCHAR(80) NOT NULL DEFAULT 'WELCOME',flow_data_json JSON NULL,status VARCHAR(30) NOT NULL DEFAULT 'active',last_message_at DATETIME NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,FOREIGN KEY(contact_id) REFERENCES dg_contacts(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS dg_messages(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,conversation_id BIGINT UNSIGNED NOT NULL,contact_id BIGINT UNSIGNED NOT NULL,wa_message_id VARCHAR(190) NULL UNIQUE,direction ENUM('IN','OUT') NOT NULL,sender_type VARCHAR(20) NOT NULL,message_type VARCHAR(40) NOT NULL,body TEXT NULL,payload_json JSON NULL,status VARCHAR(30) NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,INDEX(contact_id),INDEX(conversation_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS dg_product_categories(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL UNIQUE,active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 100,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,KEY idx_dg_product_categories_active_sort(active,sort_order)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS dg_products(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,category_id BIGINT UNSIGNED NULL,category VARCHAR(100) NOT NULL,name VARCHAR(160) NOT NULL,price DECIMAL(10,2) NOT NULL DEFAULT 0,image_path VARCHAR(255) NULL,image_url TEXT NULL,active TINYINT(1) NOT NULL DEFAULT 1,sort_order INT NOT NULL DEFAULT 100,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,KEY idx_dg_products_category_id(category_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO dg_products(category,name,price,sort_order) VALUES
('Dogos Originales','Tradicional',89,10),('Dogos Originales','Queso Dogo',109,20),('Dogos Originales','MegaJumbo',159,30),
('Dogos Internacionales','Chilidogo',129,40),('Dogos Internacionales','Alemán',139,50),('Dogos Internacionales','Argentino',149,60),('Dogos Internacionales','Polaco',149,70),
('Especialidades','Pizza dogo',119,80),('Especialidades','Choridogo',129,90),('Especialidades','Embarazado',149,100),('Especialidades','Arrachera',139,110),('Especialidades','Hawaiano',109,120),('Especialidades','Maggiedogo',149,130),
('Burgers','Clásica',149,140),('Burgers','Chessee',159,150),('Burgers','Bacon',169,160),('Burgers','Choriburger',179,170),('Burgers','Hawaiana',169,180),('Burgers','Maggieburger',199,190),('Burgers','Doblecarne',229,200),
('Entradas','Papas a la francesa',99,210),('Entradas','Choripapas',149,220),('Entradas','Aros de cebolla',129,230),('Entradas','Toritos',45,240),('Bebidas','Agua del día',35,250),('Bebidas','Refrescos',39,260),('Bebidas','Café Cold Brew',85,270);
INSERT IGNORE INTO dg_product_categories(name,active,sort_order) SELECT category,1,MIN(sort_order) FROM dg_products GROUP BY category;
UPDATE dg_products p JOIN dg_product_categories c ON c.name=p.category SET p.category_id=c.id WHERE p.category_id IS NULL;
CREATE TABLE IF NOT EXISTS dg_orders(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,folio VARCHAR(40) NOT NULL UNIQUE,contact_id BIGINT UNSIGNED NOT NULL,customer_name VARCHAR(190) NULL,items_text TEXT NOT NULL,pickup_date VARCHAR(10) NULL,pickup_time VARCHAR(80) NOT NULL,comments TEXT NULL,status VARCHAR(30) NOT NULL DEFAULT 'pending',notes TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(contact_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS dg_reservations(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,contact_id BIGINT UNSIGNED NOT NULL,reservation_date VARCHAR(40) NOT NULL,people_count INT NOT NULL,reservation_time VARCHAR(40) NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'pending',notes TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(contact_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS dg_invoice_requests(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,contact_id BIGINT UNSIGNED NOT NULL,rfc VARCHAR(30) NOT NULL,payment_method VARCHAR(120) NOT NULL,tax_regime VARCHAR(190) NOT NULL,cfdi_use VARCHAR(190) NOT NULL,email VARCHAR(190) NOT NULL,status VARCHAR(30) NOT NULL DEFAULT 'pending',notes TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(contact_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS dg_invoice_files(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,invoice_id BIGINT UNSIGNED NOT NULL,media_id VARCHAR(190) NULL,mime_type VARCHAR(80) NOT NULL,file_size INT UNSIGNED NOT NULL DEFAULT 0,file_data LONGBLOB NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_dg_invoice_file_invoice(invoice_id),CONSTRAINT fk_dg_invoice_file_invoice FOREIGN KEY(invoice_id) REFERENCES dg_invoice_requests(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS dg_event_requests(id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,contact_id BIGINT UNSIGNED NOT NULL,event_type VARCHAR(190) NOT NULL,event_date VARCHAR(40) NOT NULL,start_time VARCHAR(40) NOT NULL,service_hours VARCHAR(40) NOT NULL,guest_range VARCHAR(80) NOT NULL,address TEXT NOT NULL,products_text TEXT NOT NULL,special_needs TEXT NULL,comments TEXT NULL,status VARCHAR(30) NOT NULL DEFAULT 'pending',notes TEXT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX(contact_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS dg_settings(setting_key VARCHAR(120) PRIMARY KEY,setting_value TEXT NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO dg_settings(setting_key,setting_value) VALUES('marketing_template_name','marketing_doggiebot_texto'),('marketing_template_language','es_MX'),('firebase_crm_url','https://us-central1-doggiebot.cloudfunctions.net/whatsappWebhookDoggieBot'),('location_map_url','https://maps.app.goo.gl/nVjBNKQMLfVVKS1K9'),('location_address','Hacienda Escolásticas 312 Col. Jardines de la Hacienda, Querétaro'),('menu_image_path',''),('menu_image_url','') ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value);
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
