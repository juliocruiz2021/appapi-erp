# Project Context

## Estado actual

### Hito 1 - Base Laravel 11
- Laravel 11 inicializado en este workspace con Composer.
- Paquetes instalados: `stancl/tenancy`, `php-open-source-saver/jwt-auth`, `spatie/laravel-permission`.
- Entorno alineado a MySQL local con `DB_USERNAME=root`, `DB_PASSWORD=` vacio.
- Prefijo de bases tenant definido como `TENANT_DB_PREFIX=app_`.
- Drivers operativos ajustados para API stateless:
  - `SESSION_DRIVER=file`
  - `CACHE_STORE=file`
  - `QUEUE_CONNECTION=sync`

### Hito 2 - Plomeria multitenant y auth
- `App\Providers\TenancyServiceProvider` registrado en `bootstrap/providers.php`.
- Modelo `App\Models\Tenant` planificado para operar con `HasDatabase` + `HasDomains`.
- Middleware global por segmento `/{tenant}/api/v1/...` integrado en `bootstrap/app.php`.
- Guard JWT `api` agregado en `config/auth.php`.
- Manejo de excepciones de autenticacion ajustado para que las rutas API respondan `401` JSON aun cuando el cliente no envie `Accept: application/json`.
- `spatie/laravel-permission` configurado con cache `array` para evitar contaminacion cruzada entre tenants.
- `stancl/tenancy` ajustado para:
  - usar `App\Models\Tenant`
  - tomar `CENTRAL_DOMAINS` desde `.env`
  - crear bases tenant con `TENANT_DB_PREFIX`
  - evitar el bootstrapper de cache basado en tags

### Hito 3 - Provisioning y rutas tenant
- Servicio `TenantProvisioningService` creado para:
  - registrar empresas
  - asegurar creacion fisica de la base tenant
  - correr migraciones tenant
  - sembrar usuario admin inicial con rol `SuperAdmin`
  - reprovisionar tenants idempotentemente si la metadata central fue reiniciada pero la BD fisica ya existe
- Endpoint central `POST /system/register` implementado.
- Endpoints tenant implementados:
  - `POST /{tenant}/api/v1/login`
  - `GET /{tenant}/api/v1/me`
- Ruta canonica fijada y validada como `/{tenant}/api/v1/...`.
- La resolucion del tenant por segmento acepta tanto `tenant_id` como el alias/path registrado en `domains`.
- Compatibilidad restaurada para clientes legacy que aun llaman `POST /api/v1/{tenant}/login` sin sacrificar la ruta canonica.
- Migraciones de tenant separadas en `database/migrations/tenant`:
  - `users`
  - `roles`, `permissions` y pivotes de Spatie
- La migracion de permisos fue removida del contexto central para mantener aislamiento total.

### Hito 4 - Demo operativa y entregables
- Seeder `DemoTenantSeeder` agregado e integrado al `DatabaseSeeder`.
- Tenant demo requerido por el brief:
  - `tenant_id`: `demo1`
  - `path`: `demo1`
  - `database`: `app_demo1`
  - admin: `admin@demo1.com / password123`
- Coleccion Postman creada en `api_collection.json`.
- Respuesta HTTP limpiada de deprecations de PHP 8.5 filtrando `E_DEPRECATED` y `E_USER_DEPRECATED` en `public/index.php` y `artisan`.
- Validacion final completada el `2026-03-13`:
  - `php artisan db:seed --force` creo/aseguro `demo1`
  - MySQL confirma la base `app_demo1`
  - MySQL confirma tablas `users`, `roles`, `permissions` y pivotes
  - MySQL confirma usuario `admin@demo1.com`
  - MySQL confirma rol `SuperAdmin`
  - `POST /demo1/api/v1/login` devolvio JWT valido
  - `GET /demo1/api/v1/me` devolvio el usuario autenticado del tenant `demo1`

### Hito 5 - Administracion tenant de usuarios y permisos
- Servicio `TenantAccessService` agregado para:
  - sembrar permisos base de administracion
  - garantizar el rol `SuperAdmin`
  - sincronizar roles y permisos directos por usuario
  - evitar dejar al tenant sin ningun `SuperAdmin`
- CRUD tenant de usuarios implementado:
  - `GET /{tenant}/api/v1/users`
  - `POST /{tenant}/api/v1/users`
  - `GET /{tenant}/api/v1/users/{user}`
  - `PUT|PATCH /{tenant}/api/v1/users/{user}`
  - `DELETE /{tenant}/api/v1/users/{user}`
- CRUD tenant de permisos implementado:
  - `GET /{tenant}/api/v1/permissions`
  - `POST /{tenant}/api/v1/permissions`
  - `GET /{tenant}/api/v1/permissions/{permission}`
  - `PUT|PATCH /{tenant}/api/v1/permissions/{permission}`
  - `DELETE /{tenant}/api/v1/permissions/{permission}`
- Las respuestas JSON quedaron estandarizadas con envelope:
  - `success`
  - `message`
  - `data`
  - `meta.request_id`
- Las respuestas de auth tenant quedaron alineadas a JWT dentro del envelope:
  - `POST /{tenant}/api/v1/login` devuelve `data.access_token`, TTL, tenant y usuario enriquecido
  - `GET /{tenant}/api/v1/me` devuelve `data.tenant` + `data.user`
- Los permisos base protegidos del sistema son:
  - `users.view`
  - `users.create`
  - `users.update`
  - `users.delete`
  - `permissions.view`
  - `permissions.create`
  - `permissions.update`
  - `permissions.delete`
- Guardrails activos:
  - un usuario autenticado no puede borrarse a si mismo
  - no se pueden eliminar permisos base del sistema
  - no se puede dejar al tenant sin ningun usuario con rol `SuperAdmin`
- Artefactos Postman disponibles:
  - `api_collection.json` con flujo completo de auth, usuarios y permisos
  - `postman/appapi-smoke.collection.json` para smoke rapido sobre `demo1`
  - `postman/appapi-local.environment.json`
  - `postman/demo1.environment.json`
- Validacion completada el `2026-03-13`:
  - `php artisan test --filter=TenantUserPermissionCrudTest` paso con `33 assertions`
  - `GET /demo1/api/v1/me` sin token devuelve `401` JSON estandarizado
  - `POST /demo1/api/v1/login` devuelve JWT valido para `admin@demo1.com`
  - `GET /demo1/api/v1/me` devuelve el admin del tenant `demo1` dentro del envelope estandar
  - CRUD de permisos validado por HTTP real: crear, editar y borrar permiso custom
  - CRUD de usuarios validado por HTTP real: crear, editar, autenticar y borrar usuario custom
  - `DELETE /demo1/api/v1/users/{admin}` devuelve `422` si el admin intenta borrarse autenticado
  - `DELETE /demo1/api/v1/permissions/{base}` devuelve `422` para permisos base protegidos

### Hito 6 - Hardening, auditoria y trazabilidad
- Seguridad adicional incorporada:
  - middleware global `AssignRequestContext` para generar `X-Request-Id`
  - headers defensivos `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` y `Cache-Control`
  - throttling por escenario:
    - `system-register`
    - `tenant-login`
    - `tenant-api`
  - middleware `tenant.jwt` que valida que el claim `tenant_id` del JWT coincida con el tenant de la ruta
- Auditoria persistente agregada en base central y en cada base tenant:
  - tabla `audit_logs` en central
  - tabla `audit_logs` en cada tenant
  - eventos auditados:
    - registro de empresas
    - login exitoso y login fallido
    - CRUD de usuarios
    - CRUD de permisos
    - intentos bloqueados por guardrails
- Buenas practicas de auditoria aplicadas:
  - almacenamiento de `request_id`, IP, user-agent, actor y recurso afectado
  - `old_values` y `new_values` para operaciones mutables
  - redaccion automatica de secretos como `password`, `token` y derivados
- Validacion de seguridad completada el `2026-03-13`:
  - un token emitido para `crudtest` no puede reutilizarse contra `crudshadow`
  - Postman fue actualizado para extraer `data.access_token`
  - el smoke HTTP real sobre `demo1` confirma claim JWT `tenant_id=demo1`

### Hito 7 - Estabilidad del entorno local
- La suite PHPUnit fue aislada a una base central dedicada:
  - `DB_DATABASE=app_central_testing`
  - `TENANT_DB_PREFIX=app_test_`
- Esto evita que `php artisan test` ejecute `migrate:fresh` sobre `app_central` y borre la metadata real de `demo1`.
- Validacion del `2026-03-13`:
  - despues de correr `php artisan test --filter=TenantUserPermissionCrudTest`, la base `app_central` conserva el tenant `demo1`
  - login confirmado tanto en `POST /demo1/api/v1/login` como en `POST /api/v1/demo1/login`

## Arquitectura objetivo
- Single codebase Laravel 11.
- Base central para metadatos del sistema y aprovisionamiento de tenants.
- Una base fisica por tenant con aislamiento completo para usuarios, JWT auth y roles/permisos.
- Identificacion de tenant por segmento de URL: `/{tenant}/api/v1/...`.

## Diseño previsto
- Contexto central:
  - Registro de empresas.
  - Tabla `tenants`.
  - Tabla `domains` usada como metadato del path/alias del tenant.
  - Tabla `audit_logs` para trazabilidad de acciones de sistema.
- Contexto tenant:
  - Tabla `users`.
  - Tablas de `spatie/laravel-permission`.
  - Autenticacion JWT por guard `api`.
  - Tabla `audit_logs` con trazabilidad aislada por empresa.

## Camino de escalado a CRM/ERP
- CRM:
  - Modulos tenant-aware para clientes, oportunidades, actividades, pipelines y comunicaciones.
  - Eventos de dominio por tenant para automatizaciones comerciales.
- ERP:
  - Modulos desacoplados por bounded context: inventario, compras, ventas, contabilidad y RRHH.
  - Jobs y colas por tenant para procesos pesados como cierres, importaciones y conciliaciones.
- Escala operativa:
  - Plantilla reusable de base tenant para acelerar provisioning.
  - Observabilidad por tenant con logs enriquecidos y metricas por base fisica.
  - Estrategia futura de lectura/escritura separada por tenant enterprise si crece la carga.

## Estado final
- API central y tenant-aware operativa en un solo codebase.
- Provisioning automatico de BD por tenant funcionando con prefijo `app_`.
- Autenticacion JWT funcional por tenant.
- JWT ligado al tenant de la URL para evitar reutilizacion cruzada entre empresas.
- Roles y permisos aislados dentro de cada base tenant.
- Auditoria persistente y respuestas JSON estandarizadas activas.
- Demo `demo1` completamente lista para pruebas manuales con Postman o curl.

## Notas operativas
- En PHP 8.5 Laravel 11 todavia muestra deprecaciones del archivo de base de datos del vendor por `PDO::MYSQL_ATTR_SSL_CA`. La configuracion local del proyecto ya fue corregida, pero el vendor aun emite ese warning en algunos comandos Artisan.
