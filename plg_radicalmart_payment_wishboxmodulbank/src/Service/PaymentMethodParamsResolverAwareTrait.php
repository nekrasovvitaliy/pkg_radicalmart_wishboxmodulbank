<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service;

use UnexpectedValueException;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Provides access to a payment method parameters resolver.
 *
 * @since 1.0.0
 */
trait PaymentMethodParamsResolverAwareTrait
{
	private ?PaymentMethodParamsResolver $paymentMethodParamsResolver = null;

	/**
	 * Get the payment method parameters resolver.
	 *
	 * @return PaymentMethodParamsResolver Payment settings resolver.
	 *
	 * @since 1.0.0
	 */
	protected function getPaymentMethodParamsResolver(): PaymentMethodParamsResolver
	{
		if ($this->paymentMethodParamsResolver instanceof PaymentMethodParamsResolver)
		{
			return $this->paymentMethodParamsResolver;
		}

		throw new UnexpectedValueException('PaymentMethodParamsResolver not set in ' . static::class . '.');
	}

	/**
	 * Set the payment method parameters resolver.
	 *
	 * @param PaymentMethodParamsResolver $paymentMethodParamsResolver Payment settings resolver.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function setPaymentMethodParamsResolver(
		PaymentMethodParamsResolver $paymentMethodParamsResolver
	): void
	{
		$this->paymentMethodParamsResolver = $paymentMethodParamsResolver;
	}
}
