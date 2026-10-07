<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */
namespace Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service;

use JsonException;
use Joomla\Registry\Registry;
use RuntimeException;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Builds signed ModulBank payment link requests from RadicalMart orders.
 *
 * @since 1.0.0
 */
final readonly class ModulBankPaymentRequestFactory
{
	/**
	 * Initialize the payment request factory.
	 *
	 * @param ModulBankSignatureService  $signatureService            ModulBank signature service.
	 * @param PaymentMethodParamsResolver $paymentMethodParamsResolver Payment settings resolver.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		private ModulBankSignatureService $signatureService,
		private PaymentMethodParamsResolver $paymentMethodParamsResolver,
	)
	{
	}

	/**
	 * Build a signed payment link request.
	 *
	 * @param object                $order               RadicalMart order data.
	 * @param array<string, string> $links               Payment flow URLs.
	 * @param Registry              $paymentMethodParams Payment method parameters.
	 *
	 * @return array<string, scalar|null> ModulBank request parameters.
	 *
	 * @throws JsonException When receipt data cannot be encoded.
	 * @throws RuntimeException When receipt data is missing.
	 *
	 * @since 1.0.0
	 */
	public function create(object $order, array $links, Registry $paymentMethodParams): array
	{
		$testMode   = (bool) $paymentMethodParams->get('test_mode', false);
		$merchantId = trim((string) $paymentMethodParams->get('merchant_id', ''));
		$secretKey  = $this->paymentMethodParamsResolver->getSecretKey($paymentMethodParams, $testMode);
		$amount     = !empty($order->receipt)
			? $order->receipt->amount
			: $order->total['final'];
		$contacts   = is_array($order->contacts ?? null) ? $order->contacts : [];
		$clientName = trim(
			($contacts['first_name'] ?? '') . ' ' . ($contacts['last_name'] ?? '')
		);

		if (!is_numeric($amount) || (float) $amount <= 0)
		{
			throw new RuntimeException('The RadicalMart order amount must be greater than zero.');
		}

		$requestParams = [
			'merchant'            => $merchantId,
			'amount'              => number_format((float) $amount, 2, '.', ''),
			'custom_order_id'     => (string) $order->number,
			'description'         => 'Payment for order ' . $order->number,
			'callback_url'        => $links['callback'],
			'callback_on_failure' => '1',
			'testing'             => $testMode ? '1' : '0',
			'send_letter'         => '1',
			'unix_timestamp'      => time(),
		];

		if ($clientName !== '')
		{
			$requestParams['client_name'] = $clientName;
		}

		if (!empty($contacts['email']))
		{
			$requestParams['client_email']    = (string) $contacts['email'];
			$requestParams['receipt_contact'] = (string) $contacts['email'];
		}

		if ((int) $paymentMethodParams->get('receipt', 0) === 1)
		{
			$receiptItems = $this->buildReceiptItems($order);

			if ($receiptItems === [])
			{
				throw new RuntimeException('The RadicalMart order does not contain receipt items.');
			}

			$requestParams['receipt_items'] = json_encode($receiptItems, JSON_THROW_ON_ERROR);
		}

		$requestParams['signature'] = $this->signatureService->sign($requestParams, $secretKey);

		return $requestParams;
	}

	/**
	 * Build receipt items accepted by ModulBank.
	 *
	 * @param object $order RadicalMart order data.
	 *
	 * @return list<array<string, float|int|string>> ModulBank receipt items.
	 *
	 * @since 1.0.0
	 */
	private function buildReceiptItems(object $order): array
	{
		$receiptItems = [];
		$items        = $order->receipt->items ?? [];

		if (!is_iterable($items))
		{
			return $receiptItems;
		}

		foreach ($items as $item)
		{
			$item = is_object($item) ? get_object_vars($item) : $item;

			if (!is_array($item))
			{
				continue;
			}

			$vat = $item['vat'] ?? 'none';

			if (is_array($vat))
			{
				$vat = $vat['type'] ?? 'none';
			}

			if (!is_string($vat) || !in_array($vat, ['none', 'vat0', 'vat10', 'vat20', 'vat110', 'vat120']))
			{
				$vat = 'none';
			}

			$receiptItem = [
				'name'           => (string) ($item['name'] ?? 'Item'),
				'quantity'       => max(0.001, (float) ($item['quantity'] ?? 1)),
				'price'          => max(0.0, (float) ($item['price'] ?? 0)),
				'sno'            => (string) ($item['sno'] ?? 'usn_income'),
				'payment_object' => (string) ($item['payment_object'] ?? 'commodity'),
				'payment_method' => (string) ($item['payment_method'] ?? 'full_prepayment'),
				'vat'            => $vat,
			];

			if (isset($item['discount_sum']) && is_numeric($item['discount_sum']))
			{
				$receiptItem['discount_sum'] = max(0.0, (float) $item['discount_sum']);
			}

			$receiptItems[] = $receiptItem;
		}

		return $receiptItems;
	}
}
