<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Tests\Integration\Enum;

use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Enum\ModulBankPaymentState;
use PHPUnit\Framework\TestCase;

/**
 * Tests the ModulBank transaction state contract.
 *
 * @since 1.0.0
 */
final class ModulBankPaymentStateTest extends TestCase
{
	/**
	 * Expose every transaction state returned by ModulBank.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testContainsAllModulBankPaymentStates(): void
	{
		self::assertSame('PROCESSING', ModulBankPaymentState::PROCESSING->value);
		self::assertSame('WAITING_FOR_3DS', ModulBankPaymentState::WAITING_FOR_3DS->value);
		self::assertSame('FAILED', ModulBankPaymentState::FAILED->value);
		self::assertSame('COMPLETE', ModulBankPaymentState::COMPLETE->value);
	}

	/**
	 * Reject an undocumented transaction state.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testRejectsUnknownPaymentState(): void
	{
		self::assertNull(ModulBankPaymentState::tryFrom('UNKNOWN'));
	}
}
