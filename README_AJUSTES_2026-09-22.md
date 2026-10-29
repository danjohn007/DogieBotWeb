# DoggieBot Web — actualización minimalista, segmentación y permisos

## Qué incluye
- Diseño más minimalista y tablas más limpias.
- Menú/Productos: activar o desactivar sin eliminar.
- Contactos: clasificación comercial de clientes.
- Plantillas: envío individual, a todos o por clasificación.
- Dashboard: Customer Journey con descripción de cada clasificación.
- Usuarios: alta de administradores o usuarios con permisos de Ver/Editar por módulo.
- Mi Perfil se conserva.

## Instalación
1. Respaldar web y BD.
2. Ejecutar `doggiebot_web_update_2026-09-22.sql` en `crmcamar_doggiebot`.
3. Subir los archivos de la web encima de `/public_html/doggiebot/`.
4. NO reemplazar `config.php`.
5. Para envíos por clasificación, desplegar también el parche de Firebase incluido por separado (`functions/index.js`).

## Permisos
- Administrador: acceso total, Usuarios y Configuración.
- Usuario: permisos independientes `Ver` y `Editar` para Dashboard, Contactos, Menú, Pedidos, Reservaciones, Facturación, Eventos y Plantillas.
- Los permisos se validan en servidor, no solo ocultando el menú.
