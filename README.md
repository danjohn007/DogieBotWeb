# DoggieBot CRM

Sube esta carpeta a `/doggiebot/`, importa `doggiebot.sql` para una instalación nueva y entra a `/doggiebot/login`.

Acceso inicial: `admin@losdogos.com` / `DoggieBotCRM#2026!`.

## Actualización de una instalación existente
1. Conserva tu `config.php` actual.
2. Ejecuta las migraciones que correspondan en orden. Para esta entrega, ejecuta `doggiebot_update_2026-09-25.sql` antes de usar Facturación con ticket.
3. La carpeta `assets/uploads/profile/` y `assets/uploads/menu/` debe permitir escritura a PHP (normalmente 755 funciona en cPanel).
4. La fotografía del ticket se guarda de forma privada en MySQL y se consulta desde `ticket.php` únicamente con sesión y permiso de Facturación.
5. Consulta `README_AJUSTES_2026-09-25.md` para el detalle de la entrega actual.
