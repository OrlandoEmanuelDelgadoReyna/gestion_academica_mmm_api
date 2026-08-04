# Handoff para Flutter

Base URL: `https://<dominio>/api/v1`. Toda petición protegida debe enviar `Authorization: Bearer <token>`.

## Flujo de sesión

1. Enviar credenciales a `POST /login`.
2. Persistir únicamente el campo `token` en almacenamiento seguro.
3. Consultar `GET /me` al restaurar la sesión.
4. Ante `401`, borrar token y redirigir al login.
5. En logout, invocar `POST /logout` antes de borrar el token local.

## Contrato de errores

Los errores siguen el formato `{ message, code, errors }`. Para `422`, `errors` contiene las claves de los campos. Flutter debe mapearlas a validación de formulario, sin depender del texto.

## Autorización

La interfaz puede usar los roles del recurso `usuario`, pero el backend es la fuente de autorización. Las opciones UI no sustituyen los permisos de servidor.
