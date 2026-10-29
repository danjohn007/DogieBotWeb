-- DoggieBot CRM + Bot - ajustes 25/09/2026
-- Ejecutar UNA sola vez sobre la base crmcamar_doggiebot ANTES de subir la nueva web/bot.
-- No elimina datos existentes.
SET NAMES utf8mb4;

-- Fotografía del ticket de facturación. La imagen se almacena en MySQL para que
-- el CRM no dependa de las URLs temporales de Meta/WhatsApp.
CREATE TABLE IF NOT EXISTS dg_invoice_files (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_id BIGINT UNSIGNED NOT NULL,
  media_id VARCHAR(190) NULL,
  mime_type VARCHAR(80) NOT NULL,
  file_size INT UNSIGNED NOT NULL DEFAULT 0,
  file_data LONGBLOB NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dg_invoice_file_invoice (invoice_id),
  CONSTRAINT fk_dg_invoice_file_invoice
    FOREIGN KEY (invoice_id) REFERENCES dg_invoice_requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pick&Go y Reservaciones quedan únicamente con los cuatro estados solicitados.
UPDATE dg_orders SET status='in_process'
WHERE status IN ('review','confirmed','processing','preparing','in_progress','process','en_proceso');
UPDATE dg_orders SET status='completed'
WHERE status IN ('complete','finished','finalized','finalizado');
UPDATE dg_orders SET status='cancelled'
WHERE status IN ('canceled','cancelado');
UPDATE dg_orders SET status='pending'
WHERE status NOT IN ('pending','in_process','completed','cancelled');

UPDATE dg_reservations SET status='in_process'
WHERE status IN ('review','confirmed','processing','preparing','in_progress','process','en_proceso');
UPDATE dg_reservations SET status='completed'
WHERE status IN ('complete','finished','finalized','finalizado');
UPDATE dg_reservations SET status='cancelled'
WHERE status IN ('canceled','cancelado');
UPDATE dg_reservations SET status='pending'
WHERE status NOT IN ('pending','in_process','completed','cancelled');

-- Imagen general del menú administrada desde el CRM.
INSERT INTO dg_settings(setting_key,setting_value) VALUES
('menu_image_path',''),
('menu_image_url','')
ON DUPLICATE KEY UPDATE setting_value=setting_value;
