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
 * Provides access to the RadicalMart payment link service.
 *
 * @since 1.0.0
 */
trait RadicalMartOrderPaymentLinkCreationServiceAwareTrait
{
	/**
	 * @var RadicalMartOrderPaymentLinkCreationService|null
	 *
	 * @since 1.0.0
	 */
	private ?RadicalMartOrderPaymentLinkCreationService $radicalMartOrderPaymentLinkCreationService = null;

	/**
	 * Get the RadicalMart payment link service.
	 *
	 * @return RadicalMartOrderPaymentLinkCreationService Payment link service.
	 *
	 * @since 1.0.0
	 */
	protected function getRadicalMartOrderPaymentLinkCreationService(): RadicalMartOrderPaymentLinkCreationService
	{
		if (
			$this->radicalMartOrderPaymentLinkCreationService
			instanceof RadicalMartOrderPaymentLinkCreationService
		)
		{
			return $this->radicalMartOrderPaymentLinkCreationService;
		}

		throw new UnexpectedValueException(
			'RadicalMartOrderPaymentLinkCreationService not set in ' . static::class . '.'
		);
	}

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
	): void
	{
		$this->radicalMartOrderPaymentLinkCreationService = $paymentLinkCreationService;
	}
}
