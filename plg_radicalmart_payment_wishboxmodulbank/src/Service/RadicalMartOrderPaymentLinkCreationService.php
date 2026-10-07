<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service;

use Exception;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Component\RadicalMart\Administrator\Helper\DebugHelper as RadicalMartDebugHelper;
use Joomla\Component\RadicalMartExpress\Administrator\Helper\DebugHelper as RadicalMartExpressDebugHelper;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Helper\IntegrationHelper;
use Throwable;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Creates ModulBank payment links for RadicalMart orders.
 *
 * @since 1.0.0
 */
final readonly class RadicalMartOrderPaymentLinkCreationService
{
	private const string EXTENSION_NAME = 'plg_radicalmart_payment_wishboxmodulbank';
	private const string PLUGIN_GROUP = 'radicalmart_payment';
	private const string PLUGIN_NAME = 'wishboxmodulbank';

	/**
	 * Initialize the RadicalMart payment link service.
	 *
	 * @param PaymentLinkCreationService    $paymentLinkCreationService    ModulBank API client.
	 * @param ModulBankPaymentRequestFactory $paymentRequestFactory         Payment request factory.
	 * @param PaymentMethodParamsResolver    $paymentMethodParamsResolver   Payment settings resolver.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		private PaymentLinkCreationService $paymentLinkCreationService,
		private ModulBankPaymentRequestFactory $paymentRequestFactory,
		private PaymentMethodParamsResolver $paymentMethodParamsResolver,
	)
	{
	}

	/**
	 * Create a payment link for a RadicalMart order.
	 *
	 * @param string                $context Payment event context.
	 * @param object                $order   RadicalMart order data.
	 * @param array<string, string> $links   Payment flow URLs.
	 *
	 * @return array{pay_instant: true, link: string} Payment redirect data.
	 *
	 * @throws Exception When the payment link cannot be created.
	 *
	 * @since 1.0.0
	 */
	public function create(string $context, object $order, array $links): array
	{
		$component    = false;
		$debug        = false;
		$debugger     = 'payment.pay';
		$debuggerFile = 'site_payment_controller.php';
		$debugAction  = 'Init plugin';
		$debugData    = ['context' => $context];

		try
		{
			$component = IntegrationHelper::getComponentFromContext($context);

			if (!is_string($component))
			{
				throw new Exception(Text::_('PLG_RADICALMART_PAYMENT_WISHBOXMODULBANK_ERROR_INCORRECT_COMPONENT'), 500);
			}

			/** @var RadicalMartDebugHelper|RadicalMartExpressDebugHelper $debug */
			$debug = IntegrationHelper::getDebugHelper($component);

			$debug::addDebug(
				$debugger,
				$debuggerFile,
				$debugAction = 'Check payment method plugin',
				'start',
				null,
				null,
				null,
				false
			);

			if (!$this->paymentMethodParamsResolver->isOrderSupported($order, self::PLUGIN_NAME))
			{
				throw new Exception(Text::_('PLG_RADICALMART_PAYMENT_WISHBOXMODULBANK_ERROR_INCORRECT_PLUGIN'), 500);
			}

			$paymentMethodParams = $this->paymentMethodParamsResolver->get($component, $order->payment->id);

			if (!$this->paymentMethodParamsResolver->isPaymentAvailable($order, $paymentMethodParams))
			{
				throw new Exception(Text::_('PLG_RADICALMART_PAYMENT_WISHBOXMODULBANK_ERROR_PAYMENT_NOT_AVAILABLE'));
			}

			$testMode   = (bool) $paymentMethodParams->get('test_mode', false);
			$merchantId = trim((string) $paymentMethodParams->get('merchant_id', ''));
			$secretKey  = $this->paymentMethodParamsResolver->getSecretKey($paymentMethodParams, $testMode);

			if ($merchantId === '' || $secretKey === '')
			{
				throw new Exception(Text::_('PLG_RADICALMART_PAYMENT_WISHBOXMODULBANK_ERROR_INCORRECT_API_ACCESS'), 403);
			}

			$debug::addDebug($debugger, $debuggerFile, $debugAction, 'success', null, null, null, false);
			$debug::addDebug(
				$debugger,
				$debuggerFile,
				$debugAction = 'Prepare ModulBank request',
				'start',
				null,
				null,
				null,
				false
			);

			$requestParams = $this->paymentRequestFactory->create($order, $links, $paymentMethodParams);

			$debug::addDebug($debugger, $debuggerFile, $debugAction, 'success');
			$debugData = [
				'request_url'  => 'https://pay.modulbank.ru/api/v1/bill/',
				'request_data' => array_diff_key($requestParams, ['signature' => true]),
			];
			$debug::addDebug(
				$debugger,
				$debuggerFile,
				$debugAction = 'Send ModulBank request',
				'start',
				null,
				$debugData
			);

			$link = $this->paymentLinkCreationService->create($requestParams);

			$debug::addDebug($debugger, $debuggerFile, $debugAction, 'success');
			$debug::addDebug(
				$debugger,
				$debuggerFile,
				$debugAction = 'Add order log',
				'start',
				null,
				null,
				null,
				false
			);
			IntegrationHelper::addOrderLog($component, $order->id, 'wishboxmodulbank_pay_success', [
				'plugin' => self::PLUGIN_NAME,
				'group'  => self::PLUGIN_GROUP,
			]);
			$debug::addDebug($debugger, $debuggerFile, $debugAction, 'success', null, ['order_id' => $order->id]);

			return [
				'pay_instant' => true,
				'link'        => $link,
			];
		}
		catch (Throwable $throwable)
		{
			$debugData['error']         = $throwable->getCode() . ': ' . $throwable->getMessage();
			$debugData['error_code']    = $throwable->getCode();
			$debugData['error_message'] = $throwable->getMessage();

			if ($debug)
			{
				$debug::addDebug($debugger, $debuggerFile, $debugAction, 'error', $debugData['error'], $debugData);
			}

			IntegrationHelper::addLog(
				self::EXTENSION_NAME . '.pay.error',
				Log::ERROR,
				$throwable->getMessage(),
				$debugData,
				$throwable->getCode()
			);

			if (is_string($component) && isset($order->id))
			{
				IntegrationHelper::addOrderLog($component, $order->id, 'wishboxmodulbank_pay_error', [
					'plugin'        => self::PLUGIN_NAME,
					'group'         => self::PLUGIN_GROUP,
					'error'         => $debugData['error'],
					'error_code'    => $debugData['error_code'],
					'error_message' => $debugData['error_message'],
				]);
			}

			throw new Exception(
				'WishboxModulBank: ' . $throwable->getMessage(),
				(int) $throwable->getCode(),
				$throwable
			);
		}
	}
}
