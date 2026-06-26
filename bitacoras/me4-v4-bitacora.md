# Bitácora · v4 — Adapter y Facade

**Nombre:** Ariana Marcela Martínez Brito  
**Fecha:** 2026-06-05  
**Tag:** `v4-bloque`  
**Módulos refactorizados:** Pasarelas de pago (`PaymentController`) y checkout (`Customer::placeOrder`)

---

## Adapter — Pagos

### 1. ¿Qué problema identifiqué?

`PaymentController::process()` tenía un `if/elseif` por cada pasarela. Cada una usa método distinto (`cobrar`, `makePayment`, `initiateTransfer`), respuesta distinta y N1co pide centavos. Agregar una pasarela nueva obligaba a tocar el controller.

```php
if ($provider === 'wompi') {
    $result = $handler->cobrar($order->total, 'USD', [...]);
    $success = $result['estado'] === 'APROBADO';
} elseif ($provider === 'n1co') {
    $result = $handler->makePayment(['amount' => (int)($order->total * 100), ...]);
    $success = $result['status'] === 'success';
}
```

### 2. ¿Qué patrón apliqué?

Adapter unifica las APIs externas bajo `PaymentGateway` con `charge()` y `refund()`.

| Clase | Rol |
|-------|-----|
| `PaymentGateway` | Target — interfaz común |
| `PaymentResult` | resultado normalizado |
| `WompiAdapter`, `N1coAdapter`, `BacTransferAdapter`, `CashPaymentGateway` | Adapter |
| `WompiHandler`, `N1coHandler`, etc. | Adaptee (legacy, sin tocar) |
| `PaymentGatewayFactory` | crea el adapter según provider |
| `PaymentController` | Client — solo llama `charge()` |

### 3. ¿Qué patrón descarté?

**Facade para pagos:** el problema no es simplificar muchas clases internas del dominio, sino **traducir interfaces incompatibles** de APIs externas. Facade no traduce `cobrar()` a `makePayment()`.

### 4. ¿Qué trade-off acepté?

Capa extra de adapters; si Wompi cambia su API solo toco `WompiAdapter`, pero hay que mantener el mapa en `PaymentGatewayFactory`.

---

## Facade — Checkout

### 1. ¿Qué problema identifiqué?

`Customer::placeOrder()` tenía ~200 líneas mezclando validación, items, descuentos, stock, pago, notificaciones y loyalty. Un solo método con 6+ responsabilidades (God Method / SRP violado).

### 2. ¿Qué patrón apliqué?

`CheckoutFacade::placeOrder()` encapsula el flujo completo de checkout. `Customer` solo delega.

| Clase | Rol |
|-------|-----|
| `CheckoutFacade` | Facade — orquesta el subsistema de checkout |
| `Customer::placeOrder()` | Client — una línea de delegación |
| Servicios internos (Discount, PaymentGatewayFactory, etc.) | subsistema |

### 3. ¿Qué patrón descarté?

**Adapter para checkout:** checkout no adapta una API externa incompatible; coordina **múltiples partes del propio sistema**. Adapter sería forzar una interfaz que no existe en el legacy.

### 4. ¿Qué trade-off acepté?

`CheckoutFacade` sigue siendo una clase grande; el beneficio es centralizar el flujo y que `Customer` deje de ser God Object en ese método. Una segunda iteración podría dividir el facade en servicios más pequeños.

---

## 5. ¿Por qué Adapter para pagos y Facade para checkout, y no al revés?

**Pagos = interfaces incompatibles.** Wompi, N1co y BAC son APIs de terceros con contratos distintos. Adapter traduce cada una al mismo `PaymentGateway` sin que el controller conozca `estado` vs `status` vs `code`. Eso es exactamente el caso del adaptador de enchufe.

**Checkout = subsistema complejo con muchas partes.** Validar vendor, calcular total, persistir orden, pagar y actualizar loyalty son pasos del **mismo proceso de negocio** que el cliente solo quiere invocar con `placeOrder()`. Facade ofrece una entrada simple a un subsistema con muchas clases, como la recepción del hotel.

**Si invertiera los patrones:** un Facade sobre Wompi/N1co no resuelve que cada handler tiene métodos distintos — seguiría habiendo `if/elseif`. Un Adapter sobre checkout no tiene sentido porque no hay una API externa que traducir; solo lógica interna repartida.

---

## 6. ¿Qué cambiaría si lo hiciera de nuevo?

Inyectaría `PaymentGateway` por provider en el controller vía factory registrada en el container, en lugar de `PaymentGatewayFactory::make()` estático.
