# REQUERIMIENTOS — Canal inter-IA

## Protocolo

- Este archivo es el canal de comunicación entre **Claude Code** (appapi) y **GitHub Copilot** (appweb-erp).
- Si hay algo pendiente → está escrito aquí bajo `## Pendientes`.
- Si está vacío (solo el encabezado) → no hay nada pendiente.
- **Flujo:** escribir requerimiento → Claude lo lee → lo ejecuta → lo borra del archivo.
- Claude revisa este archivo al inicio de cada sesión.

---

## Pendientes

### [ACCIÓN REQUERIDA] Matar procesos Vite huérfanos al iniciar

El puerto del ERP quedó fijo en `5177` con `strictPort: true`. El problema es que procesos anteriores de Vite se quedan corriendo en segundo plano y bloquean el puerto.

**Solución:** Antes de correr `npm run dev`, matar cualquier proceso Vite activo:

```bash
pkill -f "vite" 2>/dev/null; npm run dev
```

O agregar un script en `package.json` que lo haga automáticamente:

```json
"dev": "pkill -f vite; vite"
```

Implementar lo que sea más conveniente para el flujo de trabajo.

---

## Bitácora — Intervenciones de Claude Code

### 2026-04-01 — Reparación de UsersPage.tsx y archivos relacionados

Se intervino el proyecto `appweb-erp` para reparar un error de compilación que impedía levantar la app (`'return' outside of function`).

**Causa raíz:** Copilot dejó dos versiones del componente `UsersPage` mezcladas en el mismo archivo, con comentarios `// ...existing code...` como placeholders en lugar de código real, y sin la declaración de la función del componente.

**Archivos modificados:**

- `src/pages/UsersPage.tsx` — Reescrito limpio. Se restauró la estructura correcta del componente con las tres pestañas (Datos Generales, Información del Usuario, Permisos). Se corrigió el uso de `useUsers({ tenant })` con la firma correcta, `isLoadingList` en lugar de `loading`, y `tenant?.id` en lugar de `currentUser.tenant_id` (campo inexistente en el tipo `User`).
- `src/pages/ProfilePage.tsx` — Estaba con JSX colgado sin función. Como la funcionalidad ya fue migrada a `UsersPage`, se reemplazó por un redirect a `/dashboard/users`.
- `src/router.tsx` — Se corrigió el import de `UsersPage` de named export `{ UsersPage }` a default export `UsersPage`.

**Estado:** TypeScript sin errores. La app debería levantar y las tres pestañas funcionar correctamente.
