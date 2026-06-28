<?php

namespace WH1\PaygateBtcPay\XF\Data;

class Currency extends XFCP_Currency
{
	public function getCurrencyData()
	{
		$currencies = array_merge(parent::getCurrencyData(), [
			'BTC' => ['code' => 'BTC', 'symbol' => '฿', 'precision' => 8, 'phrase' => 'wh1_pg_btcpay_crypto_currency.btc', 'fa' => 'fab fa-btc']
		]);

		ksort($currencies);

		return $currencies;
	}
}