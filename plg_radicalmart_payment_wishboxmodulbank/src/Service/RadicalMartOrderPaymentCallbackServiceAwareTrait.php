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
 * Provides access to the RadicalMart payment callback service.
 *
 * @since 1.0.0
 */
trait RadicalMartOrderPaymentCallbackServiceAwareTrait
{
	private ?RadicalMartOrderPaymentCallbackService $radicalMartOrderPaymentCallbackService = null;

	/**
	 * Get the RadicalMart payment callback service.
	 *
	 * @return RadicalMartOrderPaymentCallbackService Payment callback service.
	 *
	 * @since 1.0.0
	 */
	protected function getRadicalMartOrderPaymentCallbackService(): RadicalMartOrderPaymentCallbackService
	{
		if ($this->radicalMartOrderPaymentCallbackService instanceof RadicalMartOrderPaymentCallbackService)
		{
			return $this->radicalMartOrderPaymentCallbackService;
		}

		throw new UnexpectedValueException(
			'RadicalMartOrderPaymentCallbackService not set in ' . static::class . '.'
		);
	}

	/**
	 * Set the RadicalMart payment callback service.
	 *
	 * @param RadicalMartOrderPaymentCallbackService $paymentCallbackService Payment callback service.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function setRadicalMartOrderPaymentCallbackService(
		RadicalMartOrderPaymentCallbackService $paymentCallbackService
	): void
	{
		$this->radicalMartOrderPaymentCallbackService = $paymentCallbackService;
	}
}
