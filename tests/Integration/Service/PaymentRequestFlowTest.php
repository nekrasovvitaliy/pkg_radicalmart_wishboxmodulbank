<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Tests\Integration\Service;

use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\ModulBankPaymentRequestFactory;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\ModulBankSignatureService;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\PaymentMethodParamsResolver;
use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Tests payment request construction and signing as one flow.
 *
 * @since 1.0.0
 */
final class PaymentRequestFlowTest extends TestCase
{
	/**
	 * Build a signed request containing customer and receipt data.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testBuildsAndSignsCompletePaymentRequest(): void
	{
		$signatureService = new ModulBankSignatureService;
		$requestFactory = new ModulBankPaymentRequestFactory(
			$signatureService,
			new PaymentMethodParamsResolver
		);
		$order = (object) [
			'number'   => 'RM-42',
			'total'    => ['final' => 1250.50],
			'currency' => ['code' => 'RUB'],
			'contacts' => [
				'first_name' => 'Ivan',
				'last_name'  => 'Petrov',
				'email'      => 'customer@example.test',
				'phone'      => '+79990000000',
			],
			'receipt'  => (object) [
				'amount' => 1250.50,
				'items'  => [
					[
						'name'       => 'Test product',
						'quantity'   => 1,
						'price'      => 1250.50,
						'vat'        => 'vat20',
					],
				],
			],
		];
		$paymentMethodParams = new Registry([
			'merchant_id'    => 'merchant-id',
			'test_mode'      => 1,
			'test_secret_key' => 'test-secret',
			'receipt'        => 1,
		]);

		$request = $requestFactory->create(
			$order,
			[
				'success'  => 'https://shop.example.test/payment/success',
				'callback' => 'https://shop.example.test/payment/callback',
			],
			$paymentMethodParams
		);

		self::assertSame('merchant-id', $request['merchant']);
		self::assertSame('1250.50', $request['amount']);
		self::assertSame('RM-42', $request['custom_order_id']);
		self::assertSame('https://shop.example.test/payment/callback', $request['callback_url']);
		self::assertSame('1', $request['testing']);
		self::assertSame('customer@example.test', $request['receipt_contact']);
		self::assertArrayNotHasKey('currency', $request);
		self::assertArrayNotHasKey('order_id', $request);
		self::assertArrayNotHasKey('success_url', $request);
		self::assertArrayNotHasKey('client_phone', $request);
		self::assertTrue(
			$signatureService->verify($request, 'test-secret', (string) $request['signature'])
		);

		$receiptItems = json_decode((string) $request['receipt_items'], true, 512, JSON_THROW_ON_ERROR);

		self::assertSame('Test product', $receiptItems[0]['name']);
		self::assertSame('vat20', $receiptItems[0]['vat']);
	}

	/**
	 * Detect any callback field modification after signing.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testRejectsModifiedSignedCallbackPayload(): void
	{
		$signatureService = new ModulBankSignatureService;
		$callback = [
			'testing'       => '0',
			'transaction_id'=> 'transaction-1',
			'amount'        => '1250.50',
			'state'         => 'COMPLETE',
			'order_id'      => 'RM-42',
			'currency'      => 'RUB',
			'merchant'      => 'merchant-id',
		];
		$signature = $signatureService->sign($callback, 'production-secret');

		self::assertTrue($signatureService->verify($callback, 'production-secret', $signature));

		$callback['amount'] = '1.00';

		self::assertFalse($signatureService->verify($callback, 'production-secret', $signature));
	}
}
