# Bitácora · Bloque A — State + Command

**Nombre:** Ariana Marcela Martínez Brito  
**Fecha:** 2026-05-26  
**Mini-entrega:** Bloque A (State + Command)  
**Módulos refactorizados:** Estados de orden (`Order::transitionTo`) y acciones de orden (controllers API)

---

## 1. ¿Qué problema de diseño identifiqué en el legacy?

**State — transiciones centralizadas en un solo método.**  
`Order::transitionTo()` tenía un array `$allowed` con todas las transiciones de todos los estados. Agregar o cambiar un estado obligaba a editar ese bloque, violando OCP.

```php
// app/Models/Order.php (legacy, líneas 28-38)
$allowed = [
    'created'   => ['paid', 'cancelled'],
    'paid'      => ['accepted', 'cancelled', 'refunded'],
    // ... 7 estados más en el mismo array
];
```

**Command — lógica duplicada en controllers.**  
`OrderController` y `PaymentController` repetían la secuencia `transitionTo()` → `save()` → `notify()` en `updateStatus`, `cancel`, `accept` y al procesar pagos. Un cambio en ese flujo había que copiarlo en varios lugares.

```php
// app/Http/Controllers/Api/OrderController.php (legacy)
$order->transitionTo('cancelled');
$order->save();
$order->notify('cancelled');
```

---

## 2. ¿Qué patrón aplicé y por qué resuelve este problema?

### State — estados de orden

Cada estado es una clase que conoce **solo sus** transiciones permitidas. `Order` actúa como **Context** y delega en el estado actual.

| Clase | Rol |
|-------|-----|
| `OrderState` | State — contrato de transición |
| `CreatedOrderState`, `PaidOrderState`, … | ConcreteState — una por estado |
| `AbstractOrderState` | lógica común de validación |
| `OrderStateFactory` | crea el estado según `$order->status` |
| `Order::transitionTo()` | Context — delega sin conocer reglas de cada estado |

### Command — acciones sobre órdenes

Cada cambio de estado con persistencia y notificación es un **comando** ejecutable.

| Clase | Rol |
|-------|-----|
| `OrderCommand` | Command — interfaz `execute()` |
| `TransitionOrderCommand` | ConcreteCommand — transición + save + notify |
| `OrderCommandInvoker` | Invoker — ejecuta cualquier comando |
| `OrderController`, `PaymentController` | Client — crean y despachan el comando |

---

## 3. ¿Qué patrón descarté y por qué?

**Strategy para estados:** descartado porque no hay algoritmos intercambiables del mismo tipo; hay una **máquina de estados** con reglas distintas por estado. Strategy modelaría “cómo calcular”, no “a dónde puedo ir desde aquí”.

**State para las acciones del controller:** descartado porque el controller no cambia de estado interno; necesita **encapsular una operación completa** (transición + persistir + notificar). Eso es Command, no State.

---

## 4. ¿Qué trade-off acepté?

- **Más archivos:** 9 estados concretos + factory + 3 clases de Command (~13 archivos nuevos).
- **Factory centralizada:** agregar un estado nuevo implica una clase + entrada en `OrderStateFactory::MAP`.
- **Estado en memoria, no en BD:** el objeto State se reconstruye desde el string `status`; no hay columna extra, pero la lógica vive repartida entre modelo y clases State.
- **Sin historial undo:** Command no guarda cola de deshacer; aceptable porque el dominio no lo pide y los tests no lo exigen.

---

## 5. ¿Qué cambiaría si tuvieras que hacerlo de nuevo?

Haría que `Order::transitionTo()` también persista y notifique internamente, o usaría comandos específicos (`CancelOrderCommand`) si alguna acción necesita pasos extra distintos al flujo genérico.
