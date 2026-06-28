<?php

namespace WH1\PaygateBtcPay\Payment;

use Exception;
use XF;
use XF\Entity\PaymentProfile;
use XF\Entity\PurchaseRequest;
use XF\Http\Request;
use XF\Mvc\Controller;
use XF\Payment\AbstractProvider;
use XF\Payment\CallbackState;
use XF\PrintableException;
use XF\Purchasable\Purchase;

class BtcPay extends AbstractProvider
{
	public function getTitle(): string
	{
		return 'BTCPay Server';
	}

	public function getBtcPayEndpoint(PaymentProfile $paymentProfile): string
	{
		return $paymentProfile->options['base_url'] . '/api/v1';
	}

	public function verifyConfig(array &$options, &$errors = []): bool
	{
		if (empty($options['api_key']) || empty($options['store_id']) || empty($options['base_url']))
		{
			$errors[] = XF::phrase('wh1_btcpay_errors_you_must_provide_all_data');
		}
		if ($errors)
		{
			return false;
		}

		return true;
	}

	protected function getPaymentParams(PurchaseRequest $purchaseRequest, Purchase $purchase): array
	{
		$language = XF::language();

		$paymentProfile = $purchase->paymentProfile;
		$profileOptions = $paymentProfile->options;

		$params = [
			'metadata' => [
				'orderId'    => $purchaseRequest->request_key,
				'buyerEmail' => $purchaseRequest->extra_data['email'] ?? $purchase->purchaser->email,
				'buyerName'  => $purchase->purchaser->username ?? ""
			],

			'checkout' => [
				'speedPolicy'           => 'HighSpeed',
				'defaultPaymentMethod'  => 'BTC',
				'expirationMinutes'     => 90,
				'monitoringMinutes'     => 90,
				'paymentTolerance'      => 0,
				'redirectURL'           => $purchase->returnUrl,
				'redirectAutomatically' => true,
				'requiresRefundEmail'   => false,
				'defaultLanguage'       => $language->getLanguageCode()
			],

			'amount'   => $purchase->cost,
			'currency' => $purchase->currency
		];

		return $params;
	}

	public function initiatePayment(Controller $controller, PurchaseRequest $purchaseRequest, Purchase $purchase): XF\Mvc\Reply\AbstractReply
	{
		$params = $this->getPaymentParams($purchaseRequest, $purchase);
		$profileOptions = $purchase->paymentProfile->options;

		$type = $profileOptions['type'] ?? 'redirect';

		$response = XF::app()->http()->client()->post($this->getBtcPayEndpoint($purchase->paymentProfile) . "/stores/{$profileOptions['store_id']}/invoices", 
		[
			'headers'    => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'token ' . $profileOptions['api_key']
			],

			'json'       => $params,
			'exceptions' => false
		]);

		if ($response)
		{
			$responseJson = json_decode($response->getBody()->getContents(), true);

			if ($response->getStatusCode() == 200 && !empty($responseJson['id']))
			{
				$invoiceId = $responseJson['id'];

				$purchaseRequest->fastUpdate('provider_metadata', $invoiceId);

				if ($type == 'redirect')
				{
					return $controller->redirect($responseJson['checkoutLink']);
				}

				if ($type == 'widget')
				{
					$viewParams = [
						'baseUrl'   => $profileOptions['base_url'],
						'invoiceId' => $invoiceId,

						'cancelUrl' => $purchase->cancelUrl,
						'returnUrl' => $purchase->returnUrl
					];

					return $controller->view('WH1\PaygateBtcPay:Payment\Initiate', 'wh1_payment_initiate_btcpay', $viewParams);
				}

				if ($type == 'internal')
				{
					return $controller->redirect($controller->buildLink('purchase/process', null, ['request_key' => $purchaseRequest->request_key]));
				}
			}

			if (!empty($responseJson[0]['message']))
			{
				return $controller->error($responseJson[0]['message']);
			}
		}

		return $controller->error(XF::phrase('something_went_wrong_please_try_again'));
	}

	public function processPayment(Controller $controller, PurchaseRequest $purchaseRequest, PaymentProfile $paymentProfile, Purchase $purchase)
	{
		$profileOptions = $purchase->paymentProfile->options;
		$invoiceId = $purchaseRequest->provider_metadata;

		$type = $profileOptions['type'] ?? 'redirect';

		if ($type == 'internal')
		{
			$invoice = $this->getInvoice($paymentProfile, $invoiceId);
			if (!empty($invoice))
			{
				if ($controller->request()->filter('json', 'bool'))
				{
					$view = $controller->view();

					$view->setResponseType('json');
					$view->setJsonParam('invoice', [
						'id'             => $invoice['id'],
						'status'         => mb_strtolower($invoice['status']),
						'expirationTime' => $invoice['expirationTime'],
						'createdTime'    => $invoice['createdTime']
					]);

					return $view;
				}

				if ($invoice['expirationTime'] <= XF::$time)
				{
					return $controller->error(XF::phrase('wh1_btcpay_errors_invoice_expired'));
				}

				if (in_array($invoice['status'], [
					'Invalid',
					'Sd'
				]))
				{
					return $controller->error(XF::phrase('wh1_btcpay_errors_invoice_invalid'));
				}

				if ($this->invoiceActivate($paymentProfile, $invoiceId))
				{
					$invoicePaymentData = $this->getInvoicePaymentData($paymentProfile, $invoiceId);

					$purchaseRequest->fastUpdate('extra_data', array_merge($purchaseRequest->extra_data, [
						'btc_amount' => $invoicePaymentData['amount']
					]));

					if ($invoicePaymentData['paymentMethod'] == 'BTC')
					{
						$viewParams = [
							'invoiceId'       => $invoiceId,
							'invoice'         => $invoice,
							'invoicePayment'  => $invoicePaymentData,
							'purchaseRequest' => $purchaseRequest,

							'cancelUrl' => $purchase->cancelUrl,
							'returnUrl' => $purchase->returnUrl
						];

						return $controller->view('WH1\PaygateBtcPay:Payment\Initiate', 'wh1_payment_initiate_btcpay_internal', $viewParams);
					}
				}
			}
		}

		return $controller->error(XF::phrase('something_went_wrong_please_try_again'));
	}

	public function setupCallback(Request $request): CallbackState
	{
		$state = new CallbackState();

		$state->rawInput = $request->getInputRaw();

		$jsonArray = json_decode($state->rawInput, true) ?? [];

		$state->input = $request->getInputFilterer()->filterArray(['bill' => $jsonArray], [
			'bill' => 'array'
		]);

		$state->signature = $request->getServer('HTTP_BTCPAY_SIG', null);
		$state->transactionId = $state->input['bill']['invoiceId'] ?? null;

		$state->_INPUT = array_merge($request->getInputForLogs(), [
			'ip'        => $request->getIp(),
			'referer'   => $request->getReferrer(),
			'signature' => $state->signature
		]);

		$state->httpCode = 200;

		return $state;
	}

	public function validateCallback(CallbackState $state): bool
	{
		if ($state->transactionId)
		{
			$state->purchaseRequest = XF::em()->findOne('XF:PurchaseRequest', [
				'provider_metadata' => $state->transactionId
			]);

			if ($state->purchaseRequest)
			{
				$paymentProfile = $state->getPaymentProfile();
				$options = $paymentProfile->options;

				if (!empty($options['api_key']) && !empty($options['store_id']) && !empty($options['base_url']))
				{
					$generatedSignature = hash_hmac('sha256', $state->rawInput, $options['secret_key']);

					if (hash_equals(str_replace('sha256=', '', $state->signature), $generatedSignature))
					{
						return true;
					}

					$state->logType = 'error';
					$state->logMessage = 'Invalid signature.';

					return false;
				}

				$state->logType = 'error';
				$state->logMessage = 'Invalid api_key.';

				return false;
			}

			$state->logType = 'error';
			$state->logMessage = 'PurchaseRequest not found!';

			return false;
		}

		$state->logType = 'error';
		$state->logMessage = 'Callback not validated!';

		return false;
	}

	public function validatePurchaseRequest(CallbackState $state): bool
	{
		$paymentProfile = $state->getPaymentProfile();
		$options = $paymentProfile->options;

		$requestUrl = $this->getBtcPayEndpoint($paymentProfile) . "/stores/{$options['store_id']}/invoices/{$state->transactionId}";

		$response = XF::app()->http()->client()->get($requestUrl, 
		[
			'headers'    => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'token ' . $options['api_key']
			],
			'exceptions' => false
		]);

		if ($response)
		{
			$responseJson = json_decode($response->getBody()->getContents(), true);

			if ($response->getStatusCode() == 200)
			{
				$state->invoiceData = $responseJson;

				return true;
			}

			if (!empty($responseJson[0]['message']))
			{
				$state->logType = 'error';
				$state->logMessage = $responseJson[0]['message'];

				return false;
			}
		}

		$state->logType = 'error';
		$state->logMessage = 'Something went wrong with invoice check request. Code: ' . $response->getStatusCode();

		return false;
	}

	public function validatePurchasableData(CallbackState $state)
	{
		$state->costAmount = $state->invoiceData['amount'] ?? null;
		$state->costCurrency = $state->invoiceData['currency'] ?? null;

		return true;
	}

	public function validateCost(CallbackState $state): bool
	{
		$purchaseRequest = $state->getPurchaseRequest();

		$costValidated = round($state->costAmount, 2) == round($purchaseRequest->cost_amount, 2)
			&& $state->costCurrency == $purchaseRequest->cost_currency;

		if ($costValidated)
		{
			return true;
		}

		$state->logType = 'error';
		$state->logMessage = 'Invalid Cost or Currency.';

		return false;
	}

	public function getPaymentResult(CallbackState $state): void
	{
		if ($state->invoiceData['status'] == 'Settled')
		{
			$state->paymentResult = CallbackState::PAYMENT_RECEIVED;
		}
	}

	public function completeTransaction(CallbackState $state)
	{
		$state->purchaseRequest->extra_data = array_merge($state->purchaseRequest->extra_data, [
			'paid' => true
		]);

		parent::completeTransaction($state); 
	}

	public function prepareLogData(CallbackState $state): void
	{
		$state->logDetails = array_merge($state->_INPUT, [
			'invoiceData' => $state->invoiceData ?? []
		]);
	}

	protected $supportedCurrencies = [
		'RUB', 'USD', 'EUR', 'UAH', 'BTC'
	];

	public function supportsRecurring(PaymentProfile $paymentProfile, $unit, $amount, &$result = self::ERR_NO_RECURRING): bool
	{
		$result = self::ERR_NO_RECURRING;

		return false;
	}

	protected function getSupportedRecurrenceRanges(): array
	{
		return [];
	}

	public function verifyCurrency(PaymentProfile $paymentProfile, $currencyCode): bool
	{
		return in_array($currencyCode, $this->supportedCurrencies);
	}

	protected function invoiceActivate(PaymentProfile $profile, $invoiceId): bool
	{
		$profileOptions = $profile->options;

		$requestUrl = $this->getBtcPayEndpoint($profile) . "/stores/{$profileOptions['store_id']}/invoices/{$invoiceId}/payment-methods/BTC/activate";
		$response = XF::app()->http()->client()->post($requestUrl, [
			'headers'    => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'token ' . $profileOptions['api_key']
			],
			'exceptions' => false
		]);

		if ($response)
		{
			$responseJson = json_decode($response->getBody()->getContents(), true);

			if ($response->getStatusCode() == 200)
			{
				return true;
			}

			if (!empty($responseJson['message']))
			{
				throw new PrintableException($responseJson['message']);
			}
		}

		return false;

	}

	protected function getInvoice(PaymentProfile $profile, $invoiceId): array
	{
		$profileOptions = $profile->options;

		$requestUrl = $this->getBtcPayEndpoint($profile) . "/stores/{$profileOptions['store_id']}/invoices/{$invoiceId}";
		$response = XF::app()->http()->client()->get($requestUrl, [
			'headers'    => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'token ' . $profileOptions['api_key']
			],
			'exceptions' => false
		]);

		if ($response)
		{
			$responseJson = json_decode($response->getBody()->getContents(), true);

			if ($response->getStatusCode() == 200)
			{
				return $responseJson;
			}

			if (!empty($responseJson['message']))
			{
				throw new PrintableException($responseJson['message']);
			}
		}

		return [];
	}

	protected function getInvoicePaymentData(PaymentProfile $profile, $invoiceId)
	{
		$profileOptions = $profile->options;

		$response = XF::app()->http()->client()->get($this->getBtcPayEndpoint($profile) . "/stores/{$profileOptions['store_id']}/invoices/{$invoiceId}/payment-methods", [
			'headers'    => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'token ' . $profileOptions['api_key']
			],
			'exceptions' => false
		]);

		if ($response)
		{
			$responseJson = json_decode($response->getBody()->getContents(), true);

			if ($response->getStatusCode() == 200 && is_array($responseJson))
			{
				foreach ($responseJson as $paymentData)
				{
					if ($paymentData['paymentMethod'] == 'BTC')
					{
						return $paymentData;
					}
				}
			}

			if (!empty($responseJson['message']))
			{
				throw new PrintableException($responseJson['message']);
			}
		}

		return [];
	}
}