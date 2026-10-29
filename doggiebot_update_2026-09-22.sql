-- DoggieBot - actualización 22/09/2026
-- Ejecutar UNA VEZ sobre crmcamar_doggiebot antes de subir la nueva web/bot.

ALTER TABLE dg_admin_users
  ADD COLUMN profile_photo VARCHAR(255) NULL AFTER role;

ALTER TABLE dg_orders
  ADD COLUMN pickup_date VARCHAR(10) NULL AFTER items_text;

-- Los campos antiguos ticket_media_id y ticket_mime_type pueden quedarse en la tabla.
-- Ya no se usan ni se muestran en el CRM, para evitar una migración destructiva.
