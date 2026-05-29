# Bitácora · v2 — Strategy, Template Method, State y Command

**Nombre:** Ariana Marcela Martínez Brito  
**Fecha:** 2026-05-26  
**Tag:** `v2-bloque`  
**Módulos refactorizados:** Descuentos, Reportes, Estados de orden, Acciones de orden (API)

---

## Strategy — Descuentos (`app/Discounts/`)

### 1. Problema identificado

La lógica de cada tipo de descuento vivía en un `switch` dentro de `Customer::placeOrder()`, mezclada con el checkout. Cada tipo nuevo obligaba a editar ese método (violación de OCP).

```php
// app/Models/Customer.php (legacy, ~líneas 103–118)
switch ($discount->type) {
    case 'percentage':
        $discountTotal = $subtotal * ($discount->value / 100);
        break;
    case 'fixed_amount':
        $discountTotal = min($discount->value, $subtotal);
        break;
}
```

### 2. Patrón aplicado

| Clase | Rol |
|-------|-----|
| `DiscountStrategy` | Strategy — contrato `calculate()` |
| `PercentageDiscountStrategy`, `BogoDiscountStrategy`, etc. | ConcreteStrategy |
| `Discount` | Context — valida en `apply()` y delega |
| `DiscountStrategyFactory` | crea la estrategia según `type` |

### 3. Patrón descartado

**Template Method:** cada tipo de descuento cambia el algoritmo completo (BOGO revisa ítems, `first_purchase` consulta historial), no solo un paso de un mismo flujo.

### 4. Trade-off

Más archivos (~8 nuevos) y mapa estático en la factory; a cambio `Customer` ya no conoce los tipos concretos.

---

## Template Method — Reportes (`app/Reports/`)

### 1. Problema identificado

CSV, Excel y PDF repetían el mismo flujo de 5 pasos; solo cambiaba el formateo.

```php
// app/Reports/CsvReportGenerator.php (legacy)
// Solo el paso 'format' varía. Los otros 4 pasos son idénticos.
public function generate(array $params): string { ... }
```

### 2. Patrón aplicado

| Clase | Rol |
|-------|-----|
| `AbstractReportGenerator` | AbstractClass — `generate()` final con el esqueleto |
| `CsvReportGenerator`, `ExcelReportGenerator`, `PdfReportGenerator` | ConcreteClass — implementan `formatContent()`, `fileExtension()`, `logLabel()` |

### 3. Patrón descartado

**Strategy puro:** el problema era duplicar el algoritmo entero, no intercambiar una estrategia aislada. Template Method expresa “mismo proceso, distinto paso de formato”.

### 4. Trade-off

`generate()` es `final` y rígido; si un formato necesitara omitir un paso, habría que agregar más hooks.

---

## State — Estados de orden (`app/Orders/States/`)

### 1. Problema identificado

`Order::transitionTo()` concentraba todas las transiciones en un array `$allowed`. Cualquier estado nuevo obligaba a tocar ese bloque.

```php
// app/Models/Order.php (legacy, ~líneas 28–38)
$allowed = [
    'created' => ['paid', 'cancelled'],
    'paid'    => ['accepted', 'cancelled', 'refunded'],
    // ... todos los estados en un solo lugar
];
```

### 2. Patrón aplicado

| Clase | Rol |
|-------|-----|
| `OrderState` | State — contrato de transición |
| `CreatedOrderState`, `PaidOrderState`, … | ConcreteState — uno por estado |
| `OrderStateFactory` | crea el estado según `$order->status` |
| `Order::transitionTo()` | Context — delega al estado actual |

### 3. Patrón descartado

**Strategy:** no hay algoritmos intercambiables del mismo tipo; hay una máquina de estados con reglas distintas por estado.

### 4. Trade-off

9 clases de estado + factory; el objeto State se reconstruye desde el string `status` en BD (no hay columna extra).

---

## Command — Acciones de orden (`app/Orders/Commands/`)

### 1. Problema identificado

`OrderController` y `PaymentController` repetían `transitionTo()` → `save()` → `notify()` en varios métodos.

```php
// app/Http/Controllers/Api/OrderController.php (legacy)
$order->transitionTo('cancelled');
$order->save();
$order->notify('cancelled');
```

### 2. Patrón aplicado

| Clase | Rol |
|-------|-----|
| `OrderCommand` | Command — interfaz `execute()` |
| `TransitionOrderCommand` | ConcreteCommand — transición + save + notify |
| `OrderCommandInvoker` | Invoker |
| `OrderController`, `PaymentController` | Client |

### 3. Patrón descartado

**State:** el controller no cambia de estado interno; necesita encapsular una **operación completa**, no reglas de transición.

### 4. Trade-off

Sin cola de undo/historial; aceptable porque el dominio y los tests no lo exigen.

---

## 5. ¿Qué cambiaría si lo hiciera de nuevo?

- **Strategy:** mover el ajuste de `delivery_fee` de `free_delivery` dentro de la estrategia, para que `Customer` no consulte `$discount->type` después de `apply()`.
- **Command:** crear comandos específicos (`CancelOrderCommand`) si alguna acción necesita pasos extra distintos al flujo genérico.
- **General:** unificar antes las bitácoras en un solo documento por tag de entrega (`v2-bloque`), para no duplicar razonamiento entre archivos.
