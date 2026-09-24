# SilverStripe Shop Stock

Adds stock management to the SilverStripe Shop module.

[![Build Status](http://img.shields.io/travis/silvershop/silvershop-stock.svg?style=flat-square)](https://travis-ci.org/silvershop/silvershop-stock)
[![Code Quality](http://img.shields.io/scrutinizer/g/silvershop/silvershop-stock.svg?style=flat-square)](https://scrutinizer-ci.com/g/silvershop/silvershop-stock)
[![Code Coverage](http://img.shields.io/scrutinizer/coverage/g/silvershop/silvershop-stock.svg?style=flat-square)](https://scrutinizer-ci.com/g/silvershop/silvershop-stock)
[![Version](http://img.shields.io/packagist/v/silvershop/stock.svg?style=flat-square)](https://packagist.org/packages/silvershop/stock)
[![License](http://img.shields.io/packagist/l/silvershop/stock.svg?style=flat-square)](LICENSE.md)


## Installation

composer require "silvershop/stock:dev-main"

After installing the module, rebuild the database and create your first product
warehouse in the `ProductCatalogAdmin` tab.

## Feature Overview

This module provides a couple of additional models - `ProductWarehouse`,
`ProductWarehouseStock`. A warehouse is a concept of a location where quantity
of the stock is held. In a simple case, you may have a single `ProductWarehouse`
instance that contains all your stock. More complex shops may have multiple
warehouses (i.e a store and a supplier). These warehouses are managed through
the `ProductCatalogAdmin` panel in the CMS.

The `ProductWarehouseStock` object manages the relation between a `Product` or a
`ProductVariation` and contains the specific count of the product at that
particular warehouse.

After installing the module your `Product` edit screen will gain a `Stock` tab
which lists all your warehouses and the value count of the product (or
variation). Leaving a warehouse stock value as `-1` implies that this warehouse
has an unlimited quantity of this product.

For a product with variations, stock is managed per variation. As well as each
variation's `Stock` tab, a `Stock` column is shown inline on the product's
`Variations` grid for quick single-warehouse editing (it appears once at least
one warehouse exists, and edits the first warehouse's level).

When an product is added to the users cart, the quantity is on reserved as the
current order is stored in the `Order` table.

*To make sure that stock added to the cart is released on abandoned carts make
sure you have the `CartCleanupTask` task enabled as a cron job*


## Configuration

### Unlimited stock as a checkbox (opt-in)

By default a warehouse stock value of `-1` means "unlimited". That sentinel is
ambiguous where real stock can be negative or exactly `-1`. Enable an explicit
`Unlimited` boolean instead:

```yaml
SilverShop\Stock\Model\ProductWarehouseStock:
  use_unlimited_checkbox: true
```

When enabled, an `Unlimited` checkbox column appears next to `Stock` on the
product's `Variations` grid, unlimited stock is stored in the `Unlimited` field
instead of `Quantity = -1`, and `Quantity` is free to be any integer. Off by
default, so existing installs are unaffected.

> **Migration:** enabling this on an install that already uses `Quantity = -1`
> for "unlimited" requires migrating those records to `Unlimited = 1`, otherwise
> `-1` becomes a real (negative) quantity and reads as out of stock.


## TODO

* Allow prioritizing of warehouses within each product (i.e use warehouse X for
before warehouse Y) This should use a sortable grid field based on the
`ProductWarehoueStock`.
