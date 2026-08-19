# API resources

Resource accessors mirror the sections of the [public API documentation](https://tradernet.com/tradernet-api/).

## Map

| Docs section | Accessor | Notes |
|---|---|---|
| Authorization | `$tn->auth()` | Login, SMS; adopts SID into `SessionManager` |
| User data | `$tn->user()` | Profile / anketa (`getAnketa`) |
| Quotes and tickers | `$tn->quotes()` | Snapshot quotes |
| Orders | `$tn->orders()` | `buy`, `sell`, `short`, history, cancel |
| Portfolio | `$tn->portfolio()` | Positions / cash |
| Price alerts | `$tn->alerts()` | Create / list / delete |
| Watchlists | `$tn->stockLists()` | User stock lists |
| Security sessions | `$tn->securitySessions()` | Trading security session |
| Currencies | `$tn->currency()` | Cross rates |
| Reference | `$tn->reference()` | `receptionInfo()`, `securities()` |
| Requests (CPS) | `$tn->cps()` | Client instructions |
| Broker reports | `$tn->reports()` | Report downloads |
| News | `$tn->news()` | News feeds |
| Shop | `$tn->shop()` | Shop catalog |
| Tariffs | `$tn->tariff()` | Tariff list (`list()` only; select is not exposed) |
| WebSocket | `$tn->websocket()` | See [WebSocket](websocket.md) |

Static documentation tables (market lists, CPS type catalogs, order status enums published as HTML) are **not** API commands and are intentionally omitted from `ReferenceApi`.

## Orders

### 01

#### Place a buy

```php
$tn->orders()->buy('AAPL.US', quantity: 1, price: 0.0); // market
$tn->orders()->buy('AAPL.US', quantity: 1, price: 190.5); // limit
```

### 02

#### Place a sell (long close)

```php
$tn->orders()->sell('AAPL.US', quantity: 1, price: 0.0);
```

### 03

#### Open a short

```php
$tn->orders()->short('AAPL.US', quantity: 1, price: 0.0);
```

`sell()` never sends `SELL_SHORT`. Use `short()` when you intend a short open.

Quantity must be positive; price must be non-negative (`0` = market). These helpers place **live** trades — do not paste them into install/quick-start samples.

## Quotes

```php
$tn->quotes()->get(['AAPL.US', 'MSFT.US']);
```

## Portfolio

```php
$tn->portfolio()->get();
```

## Escape hatch

```php
$tn->request('getStockQuotesJson', ['tickers' => 'AAPL.US']);
```

Continue with [WebSocket](websocket.md).
