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
 * Defines a RadicalMart payment link service dependency.
 *
 * @since 1.0.0
 */
interface RadicalMartOrderPaymentLinkCreationServiceAwareInterface
{
	/**
	 * Set the RadicalMart payment link service.
	 *
	 * @param RadicalMartOrderPaymentLinkCreationService $paymentLinkCreationService Payment link service.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function setRadicalMartOrderPaymentLinkCreationService(
		RadicalMartOrderPaymentLinkCreationService $paymentLinkCreationService
	): void;
}
