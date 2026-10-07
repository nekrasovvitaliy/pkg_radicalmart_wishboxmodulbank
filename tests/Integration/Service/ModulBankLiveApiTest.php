<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Tests\Integration\Service;

use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\ModulBankSignatureService;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\PaymentLinkCreationService;
use PHPUnit\Framework\TestCase;

/**
 * Tests payment-link creation against the ModulBank test API.
 *
 * @since 1.0.0
 */
final class ModulBankLiveApiTest extends TestCase
{
	/**
	 * Create a test payment link using credentials from the environment.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testCreatesPaymentLinkUsingModulBankTestApi(): void
	{
		if (($_ENV['MODULBANK_RUN_LIVE_TESTS'] ?? '0') !== '1')
		{
			self::markTestSkipped('Set MODULBANK_RUN_LIVE_TESTS=1 to run the ModulBank live API test.');
		}

		$merchantId = trim((string) ($_ENV['MODULBANK_MERCHANT_ID'] ?? ''));
		$secretKey = trim((string) ($_ENV['MODULBANK_TEST_SECRET_KEY'] ?? ''));
		$endpoint = trim((string) ($_ENV['MODULBANK_API_URL'] ?? ''));

		self::assertNotSame('', $merchantId, 'MODULBANK_MERCHANT_ID must be configured.');
		self::assertNotSame('', $secretKey, 'MODULBANK_TEST_SECRET_KEY must be configured.');
		self::assertNotSame('', $endpoint, 'MODULBANK_API_URL must be configured.');

		$orderId = 'integration-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
		$request = [
			'merchant'        => $merchantId,
			'amount'          => '1.00',
			'custom_order_id' => $orderId,
			'description'     => 'WishBox integration test',
			'callback_url'    => 'https://example.test/payment/callback',
			'testing'         => '1',
			'send_letter'     => '0',
			'unix_timestamp'  => time(),
		];
		$signatureService = new ModulBankSignatureService;
		$request['signature'] = $signatureService->sign($request, $secretKey);
		$paymentLinkCreationService = new PaymentLinkCreationService(30, $endpoint);

		$paymentUrl = $paymentLinkCreationService->create($request);

		self::assertNotSame('', $paymentUrl);
		self::assertTrue(filter_var($paymentUrl, FILTER_VALIDATE_URL) !== false);
	}
}
