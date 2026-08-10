# MageAI_CurrencyConverter

A storefront currency exchange converter for Magento 2, powered by the free
[Frankfurter API](https://frankfurter.dev) .

## What it does

- Adds a storefront page at **`/currency-converter`**.
- Lets the buyer pick any Frankfurter-supported currency as **From** and **To**.
- Shows the live exchange rate and the converted amount.
- An inline chart shows how the rate has moved over the last 1W / 1M / 3M / 6M / 1Y.

- No CSP/CORS allow-listing needed for a third-party domain.
- Currency codes and dates from the client are validated server-side before
  being used to build the outbound URL.

## Install

Copy the module into `app/code/MageAI/CurrencyConverter`, then:

```bash
bin/magento module:enable MageAI_CurrencyConverter
bin/magento setup:upgrade
bin/magento setup:di:compile
bin/magento setup:static-content:deploy -f
bin/magento cache:flush
```

Visit `https://<your-store>/currency-converter`.

## Notes / theme compatibility

This module ships a standard Magento (Luma/Blank-based) frontend: a `.phtml`
template, a jQuery UI widget (`mage-init`), and a `_module.less` file that's
auto-imported by any theme extending Luma/Blank.

## Files

```
app/code/MageAI/CurrencyConverter/
├── registration.php
├── composer.json
├── etc/
│   ├── module.xml
│   └── frontend/routes.xml
├── Controller/
│   ├── Index/Index.php              # renders the page
│   └── Rates/
│       ├── Currencies.php           # GET  currency-converter/rates/currencies
│       ├── Rate.php                 # GET  currency-converter/rates/rate?base=&quote=
│       └── History.php              # GET  currency-converter/rates/history?base=&quote=&range=
├── Model/FrankfurterClient.php      # cached proxy to api.frankfurter.dev/v2
├── Block/Converter.php
└── view/frontend/
    ├── layout/currencyconverter_index_index.xml
    ├── layout/default.xml
    ├── requirejs-config.js
    ├── templates/converter.phtml
    └── web/
        ├── js/converter.js
        └── css/source/_module.less
```
