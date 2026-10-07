<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Enum;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Defines transaction states returned by ModulBank.
 *
 * @since 1.0.0
 */
enum ModulBankPaymentState: string
{
	case PROCESSING = 'PROCESSING';
	case WAITING_FOR_3DS = 'WAITING_FOR_3DS';
	case FAILED = 'FAILED';
	case COMPLETE = 'COMPLETE';
}
