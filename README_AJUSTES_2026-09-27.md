# DoggieBot – categorías e imágenes por producto (27/09/2026)

## Web
- Se conserva la imagen general del menú.
- Se agregan categorías administrables (crear, activar/desactivar y ordenar).
- Cada producto pertenece a una categoría.
- Cada producto puede tener fotografía propia JPG/PNG/WEBP (máx. 4 MB).
- Los productos pueden editarse, cambiar de categoría, reemplazar/quitar foto y activar/desactivar.

## Bot
Flujo Menú / Pick&Go:
1. Envía la imagen general del menú si existe.
2. Solicita elegir una categoría.
3. Envía las fotografías de los productos de esa categoría por bloques de hasta 8 para no saturar el chat.
4. Muestra la lista interactiva de esos productos.
5. El cliente selecciona el producto sin escribirlo.
6. Solicita únicamente la cantidad de porciones.
7. Si desea agregar más, regresa a categorías.

Si una categoría tiene más de 8 productos, aparece “Ver más productos”. Si existen más de 8 categorías, se paginan también.

## Base de datos
Ejecutar antes de subir la web/bot:
`doggiebot_update_2026-09-27.sql`
