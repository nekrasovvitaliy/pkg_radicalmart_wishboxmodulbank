<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Signs and verifies ModulBank request parameters.
 *
 * @since 1.0.0
 */
final readonly class ModulBankSignatureService
{
	/**
	 * Build a ModulBank signature.
	 *
	 * @param array<string, scalar|null> $params    Request parameters.
	 * @param string                     $secretKey ModulBank secret key.
	 *
	 * @return string Calculated signature.
	 *
	 * @since 1.0.0
	 */
	public function sign(array $params, string $secretKey): string
	{
		$keys = array_keys($params);
		sort($keys);
		$chunks = [];

		foreach ($keys as $key)
		{
			$value = (string) $params[$key];

			if ($value !== '' && $key !== 'signature')
			{
				$chunks[] = $key . '=' . base64_encode($value);
			}
		}

		return $this->doubleSha1(implode('&', $chunks), $secretKey);
	}

	/**
	 * Verify a ModulBank signature using a constant-time comparison.
	 *
	 * @param array<string, scalar|null> $params            Request parameters.
	 * @param string                     $secretKey         ModulBank secret key.
	 * @param string                     $receivedSignature Received signature.
	 *
	 * @return bool True when signatures match.
	 *
	 * @since 1.0.0
	 */
	public function verify(array $params, string $secretKey, string $receivedSignature): bool
	{
		return hash_equals($this->sign($params, $secretKey), $receivedSignature);
	}

	/**
	 * Hash signature data twice with the configured secret key.
	 *
	 * @param string $data      Encoded signature data.
	 * @param string $secretKey ModulBank secret key.
	 *
	 * @return string Double SHA-1 signature.
	 *
	 * @since 1.0.0
	 */
	private function doubleSha1(string $data, string $secretKey): string
	{
		for ($iteration = 0; $iteration < 2; $iteration++)
		{
			$data = sha1($secretKey . $data);
		}

		return $data;
	}
}
