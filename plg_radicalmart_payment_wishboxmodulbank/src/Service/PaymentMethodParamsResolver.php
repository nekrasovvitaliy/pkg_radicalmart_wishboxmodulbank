<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service;

use InvalidArgumentException;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper as RadicalMartParamsHelper;
use Joomla\Component\RadicalMartExpress\Administrator\Helper\ParamsHelper as RadicalMartExpressParamsHelper;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Helper\IntegrationHelper;
use Joomla\Registry\Registry;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Resolves and validates RadicalMart payment method settings.
 *
 * @since 1.0.0
 */
final readonly class PaymentMethodParamsResolver
{
	/**
	 * Get normalized payment method parameters.
	 *
	 * @param string $component       RadicalMart component name.
	 * @param int    $paymentMethodId Payment method identifier.
	 *
	 * @return Registry Payment method parameters.
	 *
	 * @since 1.0.0
	 */
	public function get(string $component, int $paymentMethodId): Registry
	{
		/** @var RadicalMartParamsHelper|RadicalMartExpressParamsHelper $paramsHelper */
		$paramsHelper = IntegrationHelper::getParamsHelper($component);

		if (!is_string($paramsHelper))
		{
			throw new InvalidArgumentException('Unsupported RadicalMart component: ' . $component);
		}

		$params = $paramsHelper::getPaymentMethodsParams($paymentMethodId);

		foreach (['merchant_id', 'secret_key', 'test_secret_key'] as $path)
		{
			$params->set($path, trim((string) $params->get($path, '')));
		}

		if ($component === IntegrationHelper::RadicalMartExpress)
		{
			$params->set('payment_available', [1]);
			$params->set('paid_status', 2);
		}

		return $params;
	}

	/**
	 * Check whether an order uses this payment plugin.
	 *
	 * @param object $order      RadicalMart order data.
	 * @param string $pluginName Payment plugin name.
	 *
	 * @return bool True when the order uses the plugin.
	 *
	 * @since 1.0.0
	 */
	public function isOrderSupported(object $order, string $pluginName): bool
	{
		return !empty($order->payment)
			&& !empty($order->payment->id)
			&& !empty($order->payment->plugin)
			&& $order->payment->plugin === $pluginName;
	}

	/**
	 * Check whether the current order status allows payment.
	 *
	 * @param object   $order               RadicalMart order data.
	 * @param Registry $paymentMethodParams Payment method parameters.
	 *
	 * @return bool True when payment is available.
	 *
	 * @since 1.0.0
	 */
	public function isPaymentAvailable(object $order, Registry $paymentMethodParams): bool
	{
		return !empty($order->status->id)
			&& in_array($order->status->id, $paymentMethodParams->get('payment_available', []));
	}

	/**
	 * Get the secret key for the selected payment mode.
	 *
	 * @param Registry $paymentMethodParams Payment method parameters.
	 * @param bool     $testMode            Test payment flag.
	 *
	 * @return string Configured secret key.
	 *
	 * @since 1.0.0
	 */
	public function getSecretKey(Registry $paymentMethodParams, bool $testMode): string
	{
		$secretKeyName = $testMode ? 'test_secret_key' : 'secret_key';

		return trim((string) $paymentMethodParams->get($secretKeyName, ''));
	}
}
