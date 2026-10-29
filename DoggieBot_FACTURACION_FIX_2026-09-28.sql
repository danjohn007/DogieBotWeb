-- DoggieBot · Facturación / fotografía de ticket
-- Compatible con MySQL 5.7 y hosting cPanel sin acceso a information_schema.
-- Es seguro ejecutarlo aunque la tabla ya exista.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_invoice_files (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id BIGINT UNSIGNED NOT NULL,
  media_id VARCHAR(190) NULL,
  mime_type VARCHAR(80) NOT NULL,
  file_size INT UNSIGNED NOT NULL DEFAULT 0,
  file_data LONGBLOB NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dg_invoice_file_invoice (invoice_id),
  KEY idx_dg_invoice_files_invoice (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SELECT COUNT(*) AS solicitudes_factura FROM dg_invoice_requests;
SELECT COUNT(*) AS tickets_guardados FROM dg_invoice_files;
