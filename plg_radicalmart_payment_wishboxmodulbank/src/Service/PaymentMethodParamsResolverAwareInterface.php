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
 * Defines a payment method parameters resolver dependency.
 *
 * @since 1.0.0
 */
interface PaymentMethodParamsResolverAwareInterface
{
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
	): void;
}
