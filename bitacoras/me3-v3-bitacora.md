# Bitácora · v3 — Factory Method, Logger y Observer

**Nombre:** Ariana Marcela Martínez Brito  
**Fecha:** 2026-05-26  
**Tag:** `v3-bloque`  
**Módulos refactorizados:** Notification, Logger, Order

---

## Factory Method — Notification

### 1. ¿Qué problema identifiqué?

`Notification::send()` usaba `if/elseif` para instanciar el servicio según el canal. Cada canal nuevo obligaba a editar el mismo método (violación de OCP).

```php
// app/Models/Notification.php (legacy, líneas 58-70)
if ($this->channel === 'email') {
    $service = new \App\Services\EmailService();
} elseif ($this->channel === 'sms') {
    $service = new \App\Services\SMSService();
} elseif ($this->channel === 'push') { ... }
```

### 2. ¿Qué patrón apliqué?

Factory Method: la factory crea el canal concreto y `send()` solo delega.

| Clase | Rol |
|-------|-----|
| `NotificationChannel` | Product |
| `EmailNotificationChannel`, `SmsNotificationChannel`, etc. | ConcreteProduct |
| `NotificationChannelFactory` | Creator — `create(string $channel)` |
| `Notification::send()` | Client |

### 3. ¿Qué patrón descarté?

**Abstract Factory:** no hay familias de productos relacionados (solo un canal por notificación). **Simple Factory** sin subclases: descartado porque cada canal tiene lógica de envío distinta y merece su clase.

### 4. ¿Qué trade-off acepté?

5 clases nuevas en `app/Notifications/Channels/`; a cambio `send()` quedó sin condicionales de canal.

---

## Logger — Service Container `singleton()`

### 1. ¿Qué problema identifiqué?

`Logger` era un singleton clásico con `getInstance()`. Todo el código dependía de estado global estático, lo que dificulta tests y acopla clases al mecanismo de creación.

```php
// app/Support/Logger.php (legacy)
private static ?self $instance = null;
public static function getInstance(): static { ... }
```

### 2. ¿Qué decisión apliqué?

**Service Container `singleton()`** en `AppServiceProvider`: el framework mantiene una única instancia y las clases resuelven `Logger` vía `app(Logger::class)` o inyección por constructor.

```php
$this->app->singleton(Logger::class, fn () => new Logger());
```

| Quién instancia | Opción elegida |
|-----------------|----------------|
| El framework | `singleton()` en el container |

### 3. ¿Qué opciones descarté?

- **Singleton clásico:** no elimina el acoplamiento estático.
- **DI manual puro:** demasiado boilerplate en modelos Eloquent que no reciben constructor DI.
- **Container `bind()`:** crearía instancias distintas; rompe el buffer `getLogs()` usado en tests.

### 4. ¿Qué trade-off acepté?

Modelos como `Order` y `Customer` usan `app(Logger::class)` en lugar de constructor; es pragmático en Eloquent pero menos puro que DI total.

---

## Observer — Order (Eloquent Events)

### 1. ¿Qué problema identifiqué?

`TransitionOrderCommand` y los controllers llamaban `$order->notify()` directamente. Eso acoplaba la transición de estado con notificaciones y efectos secundarios en un solo flujo imperativo.

```php
// TransitionOrderCommand (legacy)
$this->order->transitionTo($this->targetStatus);
$this->order->save();
$this->order->notify($this->targetStatus);
```

### 2. ¿Qué patrón apliqué?

Observer vía **Eloquent Events** de Laravel:

| Componente | Rol |
|------------|-----|
| `Order` (eventos `created` / `updated`) | Subject |
| `OrderStatusChanged` | Evento de dominio |
| `SendOrderNotifications` | Observer — notificaciones |
| `DispatchOrderSideEffects` | Observer — inventario, auditoría, métricas |
| `TransitionOrderCommand` | Client — solo transición + save |

Al cambiar `status`, `Order` dispara `OrderStatusChanged` y los listeners reaccionan sin que el command los conozca.

### 3. ¿Qué patrón descarté?

**Observer manual** (`OrderSubject` + `attach`/`detach`): Laravel ya provee events/listeners; duplicar esa infraestructura sería overhead. **Llamada directa desde controller:** descartada porque el acoplamiento seguía en el command.

### 4. ¿Qué trade-off acepté?

El evento se dispara en cualquier `save()` que cambie `status` (incluye seeders y tests); hay que asegurar que las relaciones (`customer.user`, `vendor.user`) existan o usar `loadMissing` en listeners.

---

## 5. ¿Qué cambiaría si lo hiciera de nuevo?

Registraría los listeners con `Event::listen` en un `EventServiceProvider` dedicado en lugar de `AppServiceProvider`, para separar responsabilidades de bootstrap y eventos.
