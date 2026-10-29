-- DoggieBot CRM + Bot - categorías e imágenes por producto
-- Fecha: 27/09/2026
-- Ejecutar UNA vez sobre crmcamar_doggiebot antes de subir la web/bot.
-- Conserva los productos y categorías de texto existentes.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS dg_product_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 100,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dg_product_categories_name (name),
  KEY idx_dg_product_categories_active_sort (active,sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- MySQL 5.7 no soporta ADD COLUMN IF NOT EXISTS, por eso se validan columnas.
SET @db := DATABASE();
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dg_products' AND COLUMN_NAME='category_id')=0,
  'ALTER TABLE dg_products ADD COLUMN category_id BIGINT UNSIGNED NULL AFTER id', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dg_products' AND COLUMN_NAME='image_path')=0,
  'ALTER TABLE dg_products ADD COLUMN image_path VARCHAR(255) NULL AFTER price', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dg_products' AND COLUMN_NAME='image_url')=0,
  'ALTER TABLE dg_products ADD COLUMN image_url TEXT NULL AFTER image_path', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db AND TABLE_NAME='dg_products' AND INDEX_NAME='idx_dg_products_category_id')=0,
  'ALTER TABLE dg_products ADD KEY idx_dg_products_category_id (category_id)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Crea automáticamente categorías para todos los nombres que ya existían.
INSERT IGNORE INTO dg_product_categories(name,active,sort_order)
SELECT p.category,1,MIN(p.sort_order)
FROM dg_products p
WHERE p.category IS NOT NULL AND TRIM(p.category)<>''
GROUP BY p.category;

-- Vincula los productos existentes con las nuevas categorías.
UPDATE dg_products p
JOIN dg_product_categories c ON c.name=p.category
SET p.category_id=c.id
WHERE p.category_id IS NULL;

-- Mantiene la imagen general del menú ya existente.
INSERT INTO dg_settings(setting_key,setting_value) VALUES
('menu_image_path',''),('menu_image_url','')
ON DUPLICATE KEY UPDATE setting_value=setting_value;
