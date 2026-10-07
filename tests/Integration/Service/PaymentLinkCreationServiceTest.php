<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Tests\Integration\Service;

use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\PaymentLinkCreationService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests the ModulBank HTTP client against a local HTTP server.
 *
 * @since 1.0.0
 */
final class PaymentLinkCreationServiceTest extends TestCase
{
	private static mixed $serverProcess = null;
	private static string $serverUrl;

	/**
	 * Start the local ModulBank API fixture.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public static function setUpBeforeClass(): void
	{
		$socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

		if ($socket === false)
		{
			self::fail('Unable to reserve a local test port: ' . $errorCode . ' ' . $errorMessage);
		}

		$socketName = stream_socket_get_name($socket, false);
		fclose($socket);
		$port = (int) substr((string) $socketName, strrpos((string) $socketName, ':') + 1);
		$router = dirname(__DIR__, 2) . '/fixtures/modulbank-api-router.php';
		$descriptorSpec = [
			0 => ['file', '/dev/null', 'r'],
			1 => ['file', '/dev/null', 'a'],
			2 => ['file', '/dev/null', 'a'],
		];
		self::$serverProcess = proc_open(
			[PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
			$descriptorSpec,
			$pipes
		);

		if (!is_resource(self::$serverProcess))
		{
			self::fail('Unable to start the local ModulBank API fixture.');
		}

		self::$serverUrl = 'http://127.0.0.1:' . $port;

		for ($attempt = 0; $attempt < 40; $attempt++)
		{
			$connection = @fsockopen('127.0.0.1', $port);

			if (is_resource($connection))
			{
				fclose($connection);

				return;
			}

			usleep(25_000);
		}

		self::fail('The local ModulBank API fixture did not start.');
	}

	/**
	 * Stop the local ModulBank API fixture.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public static function tearDownAfterClass(): void
	{
		if (is_resource(self::$serverProcess))
		{
			proc_terminate(self::$serverProcess);
			proc_close(self::$serverProcess);
		}
	}

	/**
	 * Send form data and return a payment URL from the API response.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testCreatesPaymentLinkThroughHttpTransport(): void
	{
		$service = new PaymentLinkCreationService(5, self::$serverUrl . '/success');

		$link = $service->create([
			'merchant'  => 'merchant-id',
			'order_id'  => 'RM-42',
			'amount'     => '1250.50',
			'signature'  => 'test-signature',
		]);

		self::assertSame('https://pay.example.test/bill/RM-42', $link);
	}

	/**
	 * Convert an API rejection into a domain-level exception.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testReportsApiRejection(): void
	{
		$service = new PaymentLinkCreationService(5, self::$serverUrl . '/rejected');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Payment request rejected by test server.');

		$service->create(['order_id' => 'RM-42']);
	}

	/**
	 * Include the ModulBank error message in an HTTP error exception.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testReportsHttpErrorResponseMessage(): void
	{
		$service = new PaymentLinkCreationService(5, self::$serverUrl . '/unauthorized');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionCode(401);
		$this->expectExceptionMessage(
			'ModulBank returned HTTP status 401. '
			. 'Криптографическая подпись: Неверное значение поля signature'
		);

		$service->create(['order_id' => 'RM-42']);
	}

	/**
	 * Reject malformed JSON returned by the API.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testRejectsInvalidJsonResponse(): void
	{
		$service = new PaymentLinkCreationService(5, self::$serverUrl . '/invalid-json');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('ModulBank returned invalid JSON.');

		$service->create(['order_id' => 'RM-42']);
	}
}
