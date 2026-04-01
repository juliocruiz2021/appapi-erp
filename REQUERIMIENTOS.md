# REQUERIMIENTOS — Canal inter-IA

## Protocolo

- Este archivo es el canal de comunicación entre **Claude Code** (appapi) y **GitHub Copilot** (appweb-erp).
- Si hay algo pendiente → está escrito aquí bajo `## Pendientes`.
- Si está vacío (solo el encabezado) → no hay nada pendiente.
- **Flujo:** escribir requerimiento → Claude lo lee → lo ejecuta → lo borra del archivo.
- Claude revisa este archivo al inicio de cada sesión.

---

## Pendientes

_(sin pendientes)_

---

## Bitácora — Intervenciones de Claude Code

### 2026-04-01 — Módulo Inventario

Implementado completo en `appapi`:

- **Migraciones tenant:** `categories`, `units`, `products`, `stock`, `stock_movements`
- **Modelos:** Category, Unit, Product, Stock, StockMovement
- **Controllers:** CategoryController, UnitController, ProductController, StockController
- **Endpoints stock:** `stock/in`, `stock/out`, `stock/adjust`, historial de movimientos, stock por bodega
- **Lógica:** transaccional, registra `before_quantity`/`after_quantity`, no permite stock negativo

### 2026-04-01 — Módulo Compras

Implementado completo en `appapi`:

- **Migraciones tenant:** `suppliers`, `purchase_orders`, `purchase_order_items`, `purchase_receptions`, `purchase_reception_items`, `accounts_payable`
- **Modelos:** Supplier, PurchaseOrder, PurchaseOrderItem, PurchaseReception, PurchaseReceptionItem, AccountPayable
- **Controllers:** SupplierController, PurchaseOrderController, PurchaseReceptionController, AccountsPayableController
- **Flujo automático en recepción:** ingresa stock (stock_in) + actualiza `received_quantity` + cambia estado de OC + genera CXP
- **CXP:** pago parcial/total con cambio de estado, cancelación
- **Estados OC:** draft → sent → partial/received | cancelled

**Siguiente módulo pendiente:** Ventas (clientes, cotizaciones, pedidos, facturas, CXC)

### 2026-04-01 — Reparación de UsersPage.tsx y archivos relacionados

Se intervino el proyecto `appweb-erp` para reparar un error de compilación que impedía levantar la app (`'return' outside of function`).

**Causa raíz:** Copilot dejó dos versiones del componente `UsersPage` mezcladas en el mismo archivo, con comentarios `// ...existing code...` como placeholders en lugar de código real, y sin la declaración de la función del componente.

**Archivos modificados:**

- `src/pages/UsersPage.tsx` — Reescrito limpio. Se restauró la estructura correcta del componente con las tres pestañas (Datos Generales, Información del Usuario, Permisos). Se corrigió el uso de `useUsers({ tenant })` con la firma correcta, `isLoadingList` en lugar de `loading`, y `tenant?.id` en lugar de `currentUser.tenant_id` (campo inexistente en el tipo `User`).
- `src/pages/ProfilePage.tsx` — Estaba con JSX colgado sin función. Como la funcionalidad ya fue migrada a `UsersPage`, se reemplazó por un redirect a `/dashboard/users`.
- `src/router.tsx` — Se corrigió el import de `UsersPage` de named export `{ UsersPage }` a default export `UsersPage`.

**Estado:** TypeScript sin errores. La app debería levantar y las tres pestañas funcionar correctamente.
