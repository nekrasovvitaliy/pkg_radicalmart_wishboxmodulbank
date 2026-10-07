<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service;

use CurlHandle;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Creates payment links through the ModulBank bill API.
 *
 * @since 1.0.0
 */
final readonly class PaymentLinkCreationService
{
	private const string DEFAULT_ENDPOINT = 'https://pay.modulbank.ru/api/v1/bill/';

	/**
	 * Initialize the ModulBank API service.
	 *
	 * @param int    $timeout  Request timeout in seconds.
	 * @param string $endpoint ModulBank bill API endpoint.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		private int $timeout = 30,
		private string $endpoint = self::DEFAULT_ENDPOINT,
	)
	{
		if ($this->timeout <= 0)
		{
			throw new InvalidArgumentException('The request timeout must be greater than zero.');
		}

		if (trim($this->endpoint) === '')
		{
			throw new InvalidArgumentException('The ModulBank endpoint must not be empty.');
		}
	}

	/**
	 * Create a ModulBank payment link.
	 *
	 * @param array<string, scalar|null> $params ModulBank request parameters.
	 *
	 * @return string Created payment page URL.
	 *
	 * @throws RuntimeException When the request or response is invalid.
	 *
	 * @since 1.0.0
	 */
	public function create(array $params): string
	{
		$curlHandle = curl_init($this->endpoint);

		if (!$curlHandle instanceof CurlHandle)
		{
			throw new RuntimeException('Unable to initialise the ModulBank request.');
		}

		$optionsSet = curl_setopt_array(
			$curlHandle,
			[
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => http_build_query($params),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => $this->timeout,
				CURLOPT_TIMEOUT        => $this->timeout,
				CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
			]
		);

		if (!$optionsSet)
		{
			throw new RuntimeException('Unable to configure the ModulBank request.');
		}

		$responseBody = curl_exec($curlHandle);

		if ($responseBody === false)
		{
			$errorMessage = curl_error($curlHandle);

			throw new RuntimeException(
				$errorMessage !== ''
					? 'The ModulBank request failed: ' . $errorMessage
					: 'The ModulBank request failed.'
			);
		}

		$statusCode = curl_getinfo($curlHandle, CURLINFO_RESPONSE_CODE);

		if ($statusCode < 200 || $statusCode >= 300)
		{
			$errorMessage = sprintf('ModulBank returned HTTP status %d.', $statusCode);

			try
			{
				$errorResponse = json_decode($responseBody, false, 512, JSON_THROW_ON_ERROR);
			}
			catch (JsonException)
			{
				$errorResponse = null;
			}

			if (
				is_object($errorResponse)
				&& is_string($errorResponse->message ?? null)
				&& trim($errorResponse->message) !== ''
			)
			{
				$errorMessage .= ' ' . trim($errorResponse->message);
			}

			throw new RuntimeException(
				$errorMessage,
				$statusCode
			);
		}

		try
		{
			$response = json_decode($responseBody, false, 512, JSON_THROW_ON_ERROR);
		}
		catch (JsonException $exception)
		{
			throw new RuntimeException('ModulBank returned invalid JSON.', 0, $exception);
		}

		if (!is_object($response))
		{
			throw new RuntimeException('ModulBank returned an invalid response.');
		}

		if (($response->status ?? null) === 'error')
		{
			$message = is_string($response->message ?? null) && $response->message !== ''
				? $response->message
				: 'ModulBank rejected the payment link request.';

			throw new RuntimeException($message);
		}

		if (($response->status ?? null) !== 'ok')
		{
			throw new RuntimeException('ModulBank returned an unknown response status.');
		}

		$paymentUrl = $response->bill->url ?? null;

		if (!is_string($paymentUrl) || $paymentUrl === '')
		{
			throw new RuntimeException('ModulBank did not return a payment link.');
		}

		return $paymentUrl;
	}
}
