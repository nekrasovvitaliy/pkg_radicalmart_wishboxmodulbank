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
use Joomla\Component\RadicalMart\Site\Model\PaymentModel as RadicalMartPaymentModel;
use Joomla\Component\RadicalMartExpress\Administrator\Helper\DebugHelper as RadicalMartExpressDebugHelper;
use Joomla\Component\RadicalMartExpress\Site\Model\PaymentModel as RadicalMartExpressPaymentModel;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Enum\ModulBankPaymentState;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Helper\IntegrationHelper;
use Throwable;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Validates ModulBank callbacks and updates RadicalMart orders.
 *
 * @since 1.0.0
 */
final readonly class RadicalMartOrderPaymentCallbackService
{
	private const string EXTENSION_NAME = 'plg_radicalmart_payment_wishboxmodulbank';
	private const string PLUGIN_GROUP = 'radicalmart_payment';
	private const string PLUGIN_NAME = 'wishboxmodulbank';

	/**
	 * Initialize the RadicalMart callback service.
	 *
	 * @param ModulBankCallbackValidator  $callbackValidator           ModulBank callback validator.
	 * @param PaymentMethodParamsResolver $paymentMethodParamsResolver Payment settings resolver.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 */
	public function __construct(
		private ModulBankCallbackValidator $callbackValidator,
		private PaymentMethodParamsResolver $paymentMethodParamsResolver,
	)
	{
	}

	/**
	 * Process a ModulBank transaction notification.
	 *
	 * @param string                                                 $context      Callback event context.
	 * @param array<string, mixed>                                   $callbackData Raw callback fields.
	 * @param RadicalMartExpressPaymentModel|RadicalMartPaymentModel $paymentModel RadicalMart payment model.
	 *
	 * @return void
	 *
	 * @throws Exception When the callback cannot be validated or processed.
	 *
	 * @since 1.0.0
	 */
	public function handle(
		string $context,
		array $callbackData,
		RadicalMartExpressPaymentModel|RadicalMartPaymentModel $paymentModel
	): void
	{
		$component    = false;
		$orderId      = 0;
		$debug        = false;
		$debugger     = 'payment.callback';
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
			$debug     = IntegrationHelper::getDebugHelper($component);

			$debugData = ['input' => $this->sanitizeCallbackData($callbackData)];

			$debug::addDebug(
				$debugger,
				$debuggerFile,
				$debugAction = 'Validate ModulBank notification',
				'start',
				null,
				$debugData,
				null,
				false
			);

			foreach ($callbackData as $fieldValue)
			{
				if (!is_scalar($fieldValue) && $fieldValue !== null)
				{
					throw new Exception('The ModulBank callback contains a non-scalar field.', 400);
				}
			}

			foreach (
				['transaction_id', 'order_id', 'state', 'merchant', 'testing', 'signature', 'amount', 'currency']
				as $requiredField
			)
			{
				if (!isset($callbackData[$requiredField]) || trim((string) $callbackData[$requiredField]) === '')
				{
					throw new Exception('ModulBank callback field is missing: ' . $requiredField, 400);
				}
			}

			$transactionId = (string) $callbackData['transaction_id'];
			$orderNumber   = (string) $callbackData['order_id'];
			$paymentState  = ModulBankPaymentState::tryFrom(
				strtoupper((string) $callbackData['state'])
			);

			if (!$paymentState instanceof ModulBankPaymentState)
			{
				throw new Exception('The ModulBank callback contains an unknown payment state.', 400);
			}

			$order = $paymentModel->getOrder($orderNumber);

			if (!$order)
			{
				$messages = [];

				foreach ($paymentModel->getErrors() as $error)
				{
					$messages[] = $error instanceof Exception ? $error->getMessage() : (string) $error;
				}

				if ($messages === [])
				{
					$messages[] = Text::_('PLG_RADICALMART_PAYMENT_WISHBOXMODULBANK_ERROR_ORDER_NOT_FOUND');
				}

				throw new Exception(implode(PHP_EOL, $messages), 404);
			}

			$orderId = (int) $order->id;

			if (!$this->paymentMethodParamsResolver->isOrderSupported($order, self::PLUGIN_NAME))
			{
				throw new Exception(Text::_('PLG_RADICALMART_PAYMENT_WISHBOXMODULBANK_ERROR_INCORRECT_PLUGIN'), 403);
			}

			$paymentMethodParams = $this->paymentMethodParamsResolver->get($component, $order->payment->id);
			$this->callbackValidator->validate($callbackData, $paymentMethodParams, $order);

			$debug::addDebug($debugger, $debuggerFile, $debugAction, 'success');

			if ($paymentState !== ModulBankPaymentState::COMPLETE)
			{
				$debug::addDebug(
					$debugger,
					$debuggerFile,
					$debugAction,
					'response',
					Text::_('PLG_RADICALMART_PAYMENT_WISHBOXMODULBANK_ERROR_INCORRECT_INPUT_STATUS'),
					['state' => $paymentState->value]
				);

				return;
			}

			if (!$this->paymentMethodParamsResolver->isPaymentAvailable($order, $paymentMethodParams))
			{
				$debug::addDebug(
					$debugger,
					$debuggerFile,
					$debugAction,
					'response',
					Text::_('PLG_RADICALMART_PAYMENT_WISHBOXMODULBANK_ERROR_PAYMENT_NOT_AVAILABLE')
				);

				return;
			}

			$paidStatusId = (int) $paymentMethodParams->get('paid_status', 0);

			if ($paidStatusId <= 0)
			{
				throw new Exception('The paid order status is not configured.', 500);
			}

			$addLog = true;

			foreach ($order->logs as $log)
			{
				if (
					($log['action'] ?? '') === 'wishboxmodulbank_paid'
					&& (string) ($log['TransactionId'] ?? '') === $transactionId
				)
				{
					$addLog = false;
					break;
				}
			}

			if (
				(int) $order->status->id !== $paidStatusId
				&& !$paymentModel->updateStatus($order->id, $paidStatusId, false, -1)
			)
			{
				$messages = [];

				foreach ($paymentModel->getErrors() as $error)
				{
					$messages[] = $error instanceof Exception ? $error->getMessage() : (string) $error;
				}

				throw new Exception(implode(PHP_EOL, $messages), 500);
			}

			if ($addLog)
			{
				$paymentModel->addLog($order->id, 'wishboxmodulbank_paid', [
					'plugin'        => self::PLUGIN_NAME,
					'group'         => self::PLUGIN_GROUP,
					'TransactionId' => $transactionId,
					'user_id'       => -1,
				]);
			}
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
				self::EXTENSION_NAME . '.callback.error',
				Log::ERROR,
				$throwable->getMessage(),
				$debugData,
				$throwable->getCode()
			);

			if (is_string($component) && $orderId > 0)
			{
				IntegrationHelper::addOrderLog($component, $orderId, 'wishboxmodulbank_callback_error', [
					'plugin'        => self::PLUGIN_NAME,
					'group'         => self::PLUGIN_GROUP,
					'error_code'    => $throwable->getCode(),
					'error_message' => $throwable->getMessage(),
				]);
			}

			throw new Exception('WishboxModulBank: ' . $throwable->getMessage(), 500, $throwable);
		}
	}

	/**
	 * Remove payment and customer secrets before writing callback data to logs.
	 *
	 * @param array<string, mixed> $callbackData Raw callback fields.
	 *
	 * @return array<string, mixed> Sanitized callback fields.
	 *
	 * @since 1.0.0
	 */
	private function sanitizeCallbackData(array $callbackData): array
	{
		foreach (
			['signature', 'salt', 'pan_mask', 'rrn', 'auth_number', 'auth_code', 'client_phone', 'client_email']
			as $sensitiveField
		)
		{
			if (array_key_exists($sensitiveField, $callbackData))
			{
				$callbackData[$sensitiveField] = '[redacted]';
			}
		}

		return $callbackData;
	}
}
