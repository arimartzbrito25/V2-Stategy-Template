# Bitácora · Mini-entrega 2 — Strategy + Template Method

**Nombre:** Ariana Marcela Martínez Brito 
**Fecha:** 2026-05-26
**Mini-entrega:** 2
**Módulos refactorizados:** Descuentos (`app/Models/Discount.php`, `app/Discounts/`) y Reportes (`app/Reports/`)

---

## 1. ¿Qué problema de diseño identifiqué en el legacy?

Había dos olores distintos, uno por módulo.

**Descuentos — violación de OCP y responsabilidad mal ubicada.** La lógica de cada tipo de descuento estaba en un `switch` dentro de `Customer::placeOrder()`, mezclada con el flujo de checkout. Cada tipo nuevo (`bogo`, `first_purchase`, etc.) obligaba a editar el mismo método del modelo de cliente, aunque el cálculo pertenece al dominio del descuento.

**Cita del legacy** (`app/Models/Customer.php`, aprox. líneas 103–118 antes del refactor):

```php
switch ($discount->type) {
    case 'percentage':
        $discountTotal = $subtotal * ($discount->value / 100);
        if ($discount->max_discount_amount) {
            $discountTotal = min($discountTotal, $discount->max_discount_amount);
        }
        break;
    case 'fixed_amount':
        $discountTotal = min($discount->value, $subtotal);
        break;
    case 'free_delivery':
        break;
}
```

Además, `Discount::apply()` no existía como punto único de cálculo: las validaciones de vigencia y usos estaban duplicadas o repartidas entre `Customer` y el modelo.

**Reportes — duplicación estructural (DRY).** `CsvReportGenerator`, `ExcelReportGenerator` y `PdfReportGenerator` repetían el mismo algoritmo de cinco pasos (validar → consultar órdenes → formatear → persistir → loguear). Solo el paso de formateo y la extensión del archivo cambiaban. Un cambio en la consulta o en la persistencia había que replicarlo tres veces.

**Cita del legacy** (`app/Reports/CsvReportGenerator.php`, comentario y método `generate`):

```php
// Solo el paso 'format' varía. Los otros 4 pasos son idénticos.
public function generate(array $params): string
{
    if (empty($params['from']) || empty($params['to'])) { ... }
    $orders = Order::whereBetween(...)->get();
    $content = $this->formatAsCsv($orders);
    // persistir + log — copiado en Pdf y Excel
}
```

---

## 2. ¿Qué patrón aplicaste y por qué resuelve este problema?

### Strategy — descuentos

El algoritmo de cálculo varía **completo** según el `type` del descuento. Strategy encapsula cada variante en su propia clase e intercambia el comportamiento en tiempo de ejecución sin tocar el contexto.

**Clases y roles:**

| Clase | Rol en el patrón |
|-------|------------------|
| `DiscountStrategy` | Strategy — contrato `calculate(Discount, Order)` |
| `PercentageDiscountStrategy`, `FixedAmountDiscountStrategy`, `BogoDiscountStrategy`, `FirstPurchaseDiscountStrategy`, `FreeDeliveryDiscountStrategy` | ConcreteStrategy — una por tipo |
| `Discount` | Context — valida reglas comunes en `apply()` y delega el cálculo |
| `DiscountStrategyFactory` | Creación de la estrategia concreta a partir del `type` (mapa estático) |
| `Customer::placeOrder()` | Cliente — arma un `Order` borrador y llama `$discount->apply($draftOrder)` |

Un tipo nuevo (p. ej. `loyalty_points`) se agrega creando una clase que implemente `DiscountStrategy` y una entrada en el `MAP` de la factory, sin modificar el `switch` de `Customer`.

### Template Method — reportes

El flujo de generación es **fijo** en orden, pero algunos pasos cambian por formato. Template Method fija el esqueleto en la clase abstracta y deja que las subclases redefinan solo los hooks.

**Clases y roles:**

| Clase | Rol en el patrón |
|-------|------------------|
| `AbstractReportGenerator` | AbstractClass — `generate()` es `final` y orquesta validate → fetch → format → persist → notify |
| `CsvReportGenerator`, `ExcelReportGenerator`, `PdfReportGenerator` | ConcreteClass — implementan `formatContent()`, `fileExtension()`, `logLabel()` |
| Métodos `validateParams`, `fetchOrders`, `persist`, `notify` | pasos invariantes del template |

Si mañana cambia el criterio de consulta de órdenes, se edita un solo lugar (`fetchOrders`).

---

## 3. ¿Qué patrón descartaste y por qué?

**Para descuentos descarté Template Method.** El flujo no es el mismo con pasos intercambiables: un BOGO inspecciona ítems por producto, `first_purchase` consulta historial del cliente, y `free_delivery` devuelve 0 en monto pero afecta el fee en otro punto del checkout. No hay un esqueleto común donde solo cambie un paso; cambia el algoritmo entero. Forzar un template obligaría a hooks vacíos o condicionales dentro de la clase base, recreando el `switch` en otro archivo.

**Para reportes descarté Strategy puro.** Sí podría modelarse “formato de salida” como estrategia intercambiable, pero el problema real era la **duplicación del algoritmo completo**, no elegir entre algoritmos alternativos del mismo tamaño. Template Method expresa explícitamente “mismo proceso, distinto paso de formato”, que coincide con el comentario del legacy (“solo el paso format varía”).

**También evité Factory Method / Abstract Factory** para descuentos en esta entrega: solo necesito instanciar una estrategia por string `type`; un mapa en `DiscountStrategyFactory` es suficiente. Una jerarquía de factories añadiría capas sin variación de familia de productos.

---

## 4. ¿Qué trade-off aceptaste?

- **Más archivos:** pasé de lógica inline en `Customer` a 1 interfaz + 5 estrategias + 1 factory + cambios en `Discount` (≈ 8 archivos nuevos solo en descuentos). Navegar el módulo exige conocer la convención de nombres.
- **Factory con mapa estático:** agregar un tipo implica tocar `DiscountStrategyFactory::MAP` (acoplamiento centralizado). A cambio, `Customer` deja de conocer los tipos concretos.
- **`Order` borrador en checkout:** `placeOrder()` construye un `Order`/`OrderItem` no persistido para que las estrategias reciban el mismo contrato que en órdenes reales. Es correcto para los tests, pero añade complejidad temporal en el cliente.
- **Template Method rígido:** `generate()` es `final`; si un formato necesitara omitir `notify()` o cambiar el orden de pasos, habría que romper el template o introducir más hooks (complejidad de la jerarquía).
- **`free_delivery` sigue con efecto colateral en `Customer`:** el monto del descuento es 0 pero el fee se anula con `if ($discount->type === 'free_delivery')`. No quedó encapsulado al 100 % en la estrategia; es deuda menor aceptada para no romper tests de integración.

---

## 5. ¿Qué cambiaría si tuvieras que hacerlo de nuevo?

Movería el ajuste de `delivery_fee` para `free_delivery` dentro de `FreeDeliveryDiscountStrategy` o un pequeño objeto resultado (`DiscountApplicationResult` con `amount` + `deliveryFeeDelta`), para que `Customer` no consulte `$discount->type` después de `apply()`.
