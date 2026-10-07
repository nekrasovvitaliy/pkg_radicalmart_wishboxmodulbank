<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Tests\Integration\Service;

use Exception;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\ModulBankCallbackValidator;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\ModulBankSignatureService;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\PaymentMethodParamsResolver;
use Joomla\Registry\Registry;
use PHPUnit\Framework\TestCase;

/**
 * Tests callback validation against payment settings and order data.
 *
 * @since 1.0.0
 */
final class ModulBankCallbackValidatorTest extends TestCase
{
	private ModulBankSignatureService $signatureService;
	private ModulBankCallbackValidator $validator;

	/**
	 * Initialize callback validation services.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	protected function setUp(): void
	{
		$this->signatureService = new ModulBankSignatureService;
		$this->validator = new ModulBankCallbackValidator(
			$this->signatureService,
			new PaymentMethodParamsResolver
		);
	}

	/**
	 * Accept a correctly signed callback matching the order.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testAcceptsMatchingCallback(): void
	{
		$callback = $this->createCallback();

		$this->validator->validate($callback, $this->createParams(), $this->createOrder());

		self::addToAssertionCount(1);
	}

	/**
	 * Reject a test callback for a production payment method.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testRejectsMismatchedPaymentMode(): void
	{
		$callback = $this->createCallback(['testing' => '1'], 'test-secret');

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('payment mode does not match');

		$this->validator->validate($callback, $this->createParams(), $this->createOrder());
	}

	/**
	 * Reject a signed callback with an incorrect amount.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testRejectsIncorrectAmount(): void
	{
		$callback = $this->createCallback(['amount' => '1.00']);

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('amount does not match');

		$this->validator->validate($callback, $this->createParams(), $this->createOrder());
	}

	/**
	 * Reject a signed callback with an incorrect currency.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testRejectsIncorrectCurrency(): void
	{
		$callback = $this->createCallback(['currency' => 'USD']);

		$this->expectException(Exception::class);
		$this->expectExceptionMessage('currency does not match');

		$this->validator->validate($callback, $this->createParams(), $this->createOrder());
	}

	/**
	 * Build signed callback fields.
	 *
	 * @param array<string, string> $overrides Callback field overrides.
	 * @param string                $secretKey Signature secret key.
	 *
	 * @return array<string, string> Signed callback fields.
	 *
	 * @since 1.0.0
	 */
	private function createCallback(array $overrides = [], string $secretKey = 'production-secret'): array
	{
		$callback = array_replace([
			'testing'        => '0',
			'transaction_id' => 'transaction-1',
			'amount'         => '1250.50',
			'state'          => 'COMPLETE',
			'order_id'       => 'RM-42',
			'currency'       => 'RUB',
			'merchant'       => 'merchant-id',
		], $overrides);
		$callback['signature'] = $this->signatureService->sign($callback, $secretKey);

		return $callback;
	}

	/**
	 * Build payment method parameters.
	 *
	 * @return Registry Payment method parameters.
	 *
	 * @since 1.0.0
	 */
	private function createParams(): Registry
	{
		return new Registry([
			'merchant_id'     => 'merchant-id',
			'test_mode'       => 0,
			'secret_key'      => 'production-secret',
			'test_secret_key' => 'test-secret',
		]);
	}

	/**
	 * Build RadicalMart order data.
	 *
	 * @return object RadicalMart order data.
	 *
	 * @since 1.0.0
	 */
	private function createOrder(): object
	{
		return (object) [
			'total'    => ['final' => 1250.50],
			'currency' => ['code' => 'RUB'],
		];
	}
}
