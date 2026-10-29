# DoggieBot Web — ajustes 25/09/2026

## Incluye
- Marca/descripcion visible como DoggieBot.
- Pick&Go y Reservaciones con estados: Pendiente, En proceso, Finalizado y Cancelado.
- Facturación con fotografía del ticket visible desde el CRM.
- Botones Ver contacto y WhatsApp/Plantilla en Pedidos, Reservaciones, Facturación y Eventos.
- Buscador en envío individual de Plantillas.
- Detalle de contacto con historial de pedidos, número de promociones y conversación.
- Menú/Productos con carga de imagen general del menú y productos activables/desactivables.
- Eventos: la web informa que el servicio puede solicitarse desde las 08:00 h.

## Instalación
1. Respaldar la base de datos y la web actual.
2. Ejecutar `doggiebot_update_2026-09-25.sql` sobre `crmcamar_doggiebot`.
3. Subir/reemplazar los archivos del PATCH en `/public_html/doggiebot/`.
4. NO reemplazar `config.php`.
5. Desplegar el `functions/index.js` del parche del bot.

La fotografía del ticket se guarda en MySQL (`dg_invoice_files`) y `ticket.php` la sirve únicamente a usuarios autenticados con permiso de Facturación.
