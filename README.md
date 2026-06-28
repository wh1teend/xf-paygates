# XenForo Paygates

A collection of payment gateway add-ons for [XenForo](https://xenforo.com/). Each add-on registers a new payment profile provider, so you can accept payments for user upgrades, paid promotions, and other XenForo transactions through an additional provider.

All add-ons are developed by [wh1teend](https://t.me/wh1teend).

## Add-ons

| Add-on | Provider | Provider ID | Min. XenForo | Version |
| --- | --- | --- | --- | --- |
| [BTCPay Server](btcpay) | Self-hosted crypto payment processor | `wh1BtcPay` | 2.0.0+ | 1.0.0 |
| [CryptoCloud](cryptocloud) | Crypto payment gateway | `wh1CryptoCloud` | 2.1.2+ | 1.0.0 |
| [LiqPay](liqpay) | Card / online payments | `wh1LiqPay` | 2.1.2+ | 1.0.1 |
| [WayForPay](wayforpay) | Card / online payments | `wh1WayForPay` | 2.1.2+ | 1.0.0 |
| [Xsolla](xsolla) | Game-oriented payment platform | `wh1Xsolla` | 2.1.2+ | 1.0.0 |

## Requirements

- PHP 7.4+
- XenForo 2.0.0+ (2.1.2+ for most add-ons — see the table above)

## Installation

Each add-on is installed in the standard XenForo way:

1. Copy the add-on directory into `src/addons/WH1/` (e.g. `src/addons/WH1/BtcPay`).
2. Install it from the Admin Control Panel under **Add-ons**, or via the CLI:
   ```
   php cmd.php xf-addon:install WH1/BtcPay
   ```
3. Create a payment profile at **Setup → Payment profiles → Add payment profile** and select the new provider, or open the direct URL, e.g.:
   ```
   /admin.php?payment-profiles/add&provider_id=wh1BtcPay
   ```
4. Fill in the credentials and webhook/callback URLs as required by each provider.

See the README inside each add-on directory for provider-specific configuration steps.

## Repository layout

```
btcpay/       BTCPay Server payment profile
cryptocloud/  CryptoCloud payment profile
liqpay/       LiqPay payment profile
wayforpay/    WayForPay payment profile
xsolla/       Xsolla payment profile
```

Each add-on follows the standard XenForo structure: an `addon.json` manifest, a `Setup.php` installer, a `Payment/` directory with the provider handler, and `_data/` with the exported XML data.

## Support

For questions and support, contact the developer on Telegram: [@wh1teend](https://t.me/wh1teend).

## Donate

If these add-ons are useful to you, you can support development:

- **BTC:** `bc1qv7v3q3ljx3ulta3sqnqyqmyz3eqva5k4xzdgqa`
- **ETH:** `0x83e1A121D3b9e0a851EDc8a6D143077e81c019C7`
- **LTC:** `ltc1qg7yuap9h0qpk0fqhay68x3n0wr4avc8qq7nxyd`
