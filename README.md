# Hospital UTC · RENACE

Aplicativo web para administrar un hospital: citas, pacientes, nacimientos, visitantes, medicamentos,
enfermedades, ventas, inventario, usuarios y bitácoras de auditoría.

Está hecho en **PHP 8.2 sin framework** y usa una base de datos **Oracle 21c**.

## Cómo está organizado

```
app/
  config/       Lectura de la configuración y conexión a Oracle
  controllers/  Reciben cada petición y deciden qué mostrar
  core/         Piezas comunes: rutas, sesión, seguridad y clases base
  models/       Consultas y operaciones con la base de datos
  views/        Pantallas en HTML
  routes.php    Lista de todas las direcciones del sistema
bin/            Herramienta para probar la conexión a Oracle
docs/           Objetos de Oracle que usa cada pantalla
public/         Única carpeta visible en internet (entrada, estilos e iconos)
tests/          Pruebas automáticas
```

Cada petición sigue este camino: `public/index.php` → `App` → ruta en `routes.php` → controlador → modelo → Oracle → vista.

Los controladores que procesan formularios heredan de `Controlador`, y los modelos de `Modelo`, para no repetir código.

## Reglas de la base de datos

El aplicativo se conecta con un usuario de permisos mínimos:

- **Para cambiar datos** llama a procedimientos de paquetes (`pkg_...`).
- **Para consultar** lee vistas (`vw_...`).
- Nunca accede directamente a las tablas.

## Ejecutarlo en una computadora

Requiere PHP 8.2 con la extensión OCI8, Oracle Instant Client y Composer.

1. Instala las dependencias: `composer install`.
2. Copia `app/config/config.example.php` como `app/config/config.local.php` y completa la conexión. Ese archivo no se sube al repositorio.
3. Comprueba la conexión: `composer db:check`.
4. Inicia el servidor: `composer serve`, y abre `http://localhost:8000`.

Para correr las pruebas: `composer test`.

## Publicación

El `Dockerfile` construye el servidor con PHP, Apache y OCI8. En el servicio de hosting se definen estas variables de entorno:

| Variable | Contenido |
|---|---|
| `DB_USERNAME` | Usuario de Oracle del aplicativo |
| `DB_PASSWORD` | Su clave |
| `DB_DSN` | Dirección, con el formato `//servidor:1521/servicio` |
| `DB_SCHEMA` | Esquema dueño de las tablas |
