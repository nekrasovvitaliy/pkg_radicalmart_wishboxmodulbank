<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service;

use Exception;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Validates signed ModulBank callback data against an order and payment settings.
 *
 * @since 1.0.0
 */
final readonly class ModulBankCallbackValidator
{
	/**
	 * Initialize the callback validator.
	 *
	 * @param ModulBankSignatureService   $signatureService            ModulBank signature service.
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
	 * Validate callback authenticity and payment attributes.
	 *
	 * @param array<string, scalar|null> $callbackData       Signed callback fields.
	 * @param Registry                   $paymentMethodParams Payment method parameters.
	 * @param object                     $order               RadicalMart order data.
	 *
	 * @return void
	 *
	 * @throws Exception When callback validation fails.
	 *
	 * @since 1.0.0
	 */
	public function validate(array $callbackData, Registry $paymentMethodParams, object $order): void
	{
		$testing = (string) ($callbackData['testing'] ?? '');

		if (!in_array($testing, ['0', '1'], true))
		{
			throw new Exception('The ModulBank callback contains an invalid testing flag.', 400);
		}

		$isTestPayment = $testing === '1';
		$configuredTestMode = (bool) $paymentMethodParams->get('test_mode', false);

		if ($isTestPayment !== $configuredTestMode)
		{
			throw new Exception('The ModulBank callback payment mode does not match the configured mode.', 403);
		}

		$merchantId = trim((string) $paymentMethodParams->get('merchant_id', ''));
		$secretKey = $this->paymentMethodParamsResolver->getSecretKey(
			$paymentMethodParams,
			$configuredTestMode
		);

		if ($merchantId === '' || $secretKey === '')
		{
			throw new Exception('The ModulBank API credentials are not configured.', 403);
		}

		if (!hash_equals($merchantId, (string) ($callbackData['merchant'] ?? '')))
		{
			throw new Exception('The ModulBank callback merchant does not match the configured merchant.', 403);
		}

		if (!$this->signatureService->verify(
			$callbackData,
			$secretKey,
			(string) ($callbackData['signature'] ?? '')
		))
		{
			throw new Exception('The ModulBank callback signature is invalid.', 403);
		}

		$callbackAmount = $callbackData['amount'] ?? null;
		$expectedAmount = !empty($order->receipt)
			? ($order->receipt->amount ?? null)
			: ($order->total['final'] ?? null);

		if (!is_numeric($callbackAmount) || !is_numeric($expectedAmount))
		{
			throw new Exception('The ModulBank callback or order amount is invalid.', 400);
		}

		$normalizedCallbackAmount = number_format((float) $callbackAmount, 2, '.', '');
		$normalizedExpectedAmount = number_format((float) $expectedAmount, 2, '.', '');

		if ($normalizedCallbackAmount !== $normalizedExpectedAmount)
		{
			throw new Exception('The ModulBank callback amount does not match the order amount.', 403);
		}

		$callbackCurrency = strtoupper(trim((string) ($callbackData['currency'] ?? '')));
		$orderCurrency = strtoupper(trim((string) ($order->currency['code'] ?? '')));

		if ($callbackCurrency === '' || $orderCurrency === '')
		{
			throw new Exception('The ModulBank callback or order currency is missing.', 400);
		}

		if (!hash_equals($orderCurrency, $callbackCurrency))
		{
			throw new Exception('The ModulBank callback currency does not match the order currency.', 403);
		}
	}
}
