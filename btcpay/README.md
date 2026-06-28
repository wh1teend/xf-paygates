
![Logo](https://user-images.githubusercontent.com/44041140/234671863-ce105ea3-3fd6-4644-a4df-63793f4b1acd.png)

# Paygate BTCPay Server

This add-on allows you to set up payment acceptance on your forum via BTCPay Server payment gateway, adding it to the list of supported.

## Instructions
1. Install the plugin in the standard XF ways.
2. Create a payment gateway `/admin.php?payment-profiles/add&provider_id=wh1BtcPay` and specify data.

![image](https://user-images.githubusercontent.com/44041140/234713935-37c55530-82e8-4aae-8a89-fc2bd5acedbe.png)

3. Here you can choose to redirect or internal, it does not matter. 

`Redirect` - will redirect to the payment page.
`internal` - a popup window with the purse and the amount.

The `base url` is a link to the btcpay installed. 
For example, `https://btcpay.example.com`

4. API key must be given these rights

![image](https://user-images.githubusercontent.com/44041140/234672427-368a857f-99b3-4260-9393-929abd971daa.png)
![image](https://user-images.githubusercontent.com/44041140/234672459-99e55420-2862-4a21-ab82-7a7b8920e1c8.png)

5. Enter the webhook address here

![image](https://user-images.githubusercontent.com/44041140/234683240-a4ebfafc-d579-4b00-af1d-8060a87c3e8a.png)

6.  How to check that everything works - after setting up a payment gateway on the forum, pay for something on this forum
Paid promotion, transaction, whatever.
To avoid sending real money, you can manually approve the payment (Mark as settled) when paying on the BTCPay invoice page

## Require
- PHP 7.4+
- XenForo 2.0.0+

## Donate
* BTC: bc1qv7v3q3ljx3ulta3sqnqyqmyz3eqva5k4xzdgqa
* ETH: 0x83e1A121D3b9e0a851EDc8a6D143077e81c019C7
* LTC: ltc1qg7yuap9h0qpk0fqhay68x3n0wr4avc8qq7nxyd
