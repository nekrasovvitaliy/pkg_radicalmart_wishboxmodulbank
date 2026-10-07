<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Extension;

use Exception;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper as RadicalMartParamsHelper;
use Joomla\Component\RadicalMart\Site\Model\PaymentModel as RadicalMartPaymentModel;
use Joomla\Component\RadicalMartExpress\Administrator\Helper\ParamsHelper as RadicalMartExpressParamsHelper;
use Joomla\Component\RadicalMartExpress\Site\Model\PaymentModel as RadicalMartExpressPaymentModel;
use Joomla\Event\SubscriberInterface;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Helper\IntegrationHelper;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\PaymentMethodParamsResolverAwareInterface;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\PaymentMethodParamsResolverAwareTrait;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\RadicalMartOrderPaymentCallbackServiceAwareInterface;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\RadicalMartOrderPaymentCallbackServiceAwareTrait;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\RadicalMartOrderPaymentLinkCreationServiceAwareInterface;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\RadicalMartOrderPaymentLinkCreationServiceAwareTrait;
use Joomla\Registry\Registry;
use stdClass;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Integrates RadicalMart payment events with ModulBank services.
 *
 * @since 1.0.0
 *
 * @noinspection PhpUnused
 */
final class WishboxModulBank extends CMSPlugin implements
	PaymentMethodParamsResolverAwareInterface,
	RadicalMartOrderPaymentCallbackServiceAwareInterface,
	RadicalMartOrderPaymentLinkCreationServiceAwareInterface,
	SubscriberInterface
{
	use PaymentMethodParamsResolverAwareTrait;
	use RadicalMartOrderPaymentCallbackServiceAwareTrait;
	use RadicalMartOrderPaymentLinkCreationServiceAwareTrait;

	/**
	 * Load the language file on instantiation.
	 *
	 * @var bool
	 *
	 * @since 1.0.0
	 */
	protected $autoloadLanguage = true;

	/**
	 * Extension name.
	 *
	 * @var string
	 *
	 * @since 1.0.0
	 */
	public string $extension = 'plg_radicalmart_payment_wishboxmodulbank';

	/**
	 * Return the Joomla events handled by this plugin.
	 *
	 * @return array<string, string> Event handler map.
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public static function getSubscribedEvents(): array
	{
		return [
			'onRadicalMartGetOrderPaymentMethods' => 'onGetOrderPaymentMethods',
			'onRadicalMartGetOrderLogs'           => 'onGetOrderLogs',
			'onRadicalMartCheckOrderPay'          => 'onCheckOrderPay',
			'onRadicalMartPaymentPay'             => 'onPaymentPay',
			'onRadicalMartPaymentCallback'        => 'onPaymentCallback',
			'onRadicalMartPrepareMethodForm'      => 'onRadicalMartPrepareMethodForm',

			'onRadicalMartExpressGetOrderPaymentMethods' => 'onGetOrderPaymentMethods',
			'onRadicalMartExpressGetOrderLogs'           => 'onGetOrderLogs',
			'onRadicalMartExpressCheckOrderPay'          => 'onCheckOrderPay',
			'onRadicalMartExpressPaymentPay'             => 'onPaymentPay',
			'onRadicalMartExpressPaymentCallback'        => 'onPaymentCallback',
			'onRadicalMartExpressPrepareConfigForm'      => 'onRadicalMartExpressPrepareConfigForm',
		];
	}

	/**
	 * Prepare RadicalMart order payment method data.
	 *
	 * @param string               $context  Payment event context.
	 * @param object               $method   Payment method data.
	 * @param array<string, mixed> $formData Submitted order form data.
	 * @param array<int, mixed>    $products Order product data.
	 * @param array<string, mixed> $currency Order currency data.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onGetOrderPaymentMethods(
		string $context,
		object $method,
		array $formData,
		array $products,
		array $currency
	): void
	{
		$method->params->set('secret_key', '');
		$method->params->set('test_secret_key', '');

		if (str_contains($context, 'com_radicalmart_express.'))
		{
			$method->params->set('payment_available', [1]);
			$method->params->set('paid_status', 2);
		}

		$method->order              = new stdClass;
		$method->order->id          = $method->id;
		$method->order->title       = $method->title;
		$method->order->code        = $method->code;
		$method->order->description = $method->description;
		$method->order->price       = [];
	}

	/**
	 * Prepare a payment log for RadicalMart order display.
	 *
	 * @param string               $context Payment event context.
	 * @param array<string, mixed> $log     Order log data.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onGetOrderLogs(string $context, array &$log): void
	{
		if (!str_contains((string) ($log['action'] ?? ''), 'wishboxmodulbank'))
		{
			return;
		}

		$event              = str_replace('wishboxmodulbank_', '', $log['action']);
		$log['action_text'] = Text::_('PLG_RADICALMART_PAYMENT_WISHBOXMODULBANK_LOGS_' . $event);

		if ($event === 'pay_error' || $event === 'callback_error')
		{
			$log['message'] = !empty($log['error_message']) ? $log['error_message'] : '';

			return;
		}

		if ($log['action'] === 'wishboxmodulbank_paid' && !empty($log['TransactionId']))
		{
			$log['message'] = Text::sprintf(
				'PLG_RADICALMART_PAYMENT_WISHBOXMODULBANK_LOGS_PAID_MESSAGE',
				$log['TransactionId']
			);
		}
	}

	/**
	 * Check whether a RadicalMart order can be paid.
	 *
	 * @param string $context Payment event context.
	 * @param object $order   RadicalMart order data.
	 *
	 * @return bool True when payment is available.
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function onCheckOrderPay(string $context, object $order): bool
	{
		$paymentMethodParamsResolver = $this->getPaymentMethodParamsResolver();

		if (!$paymentMethodParamsResolver->isOrderSupported($order, $this->_name))
		{
			return false;
		}

		$component = IntegrationHelper::getComponentFromContext($context);

		if (!is_string($component))
		{
			return false;
		}

		$paymentMethodParams = $paymentMethodParamsResolver->get($component, $order->payment->id);
		$testMode           = (bool) $paymentMethodParams->get('test_mode', false);
		$merchantId         = trim((string) $paymentMethodParams->get('merchant_id', ''));
		$secretKey          = $paymentMethodParamsResolver->getSecretKey($paymentMethodParams, $testMode);

		return $merchantId !== ''
			&& $secretKey !== ''
			&& $paymentMethodParamsResolver->isPaymentAvailable($order, $paymentMethodParams);
	}

	/**
	 * Create a payment transaction and return redirect data.
	 *
	 * @param string                $context Payment event context.
	 * @param object                $order   RadicalMart order data.
	 * @param array<string, string> $links   Payment flow URLs.
	 * @param Registry              $params  Component parameters.
	 *
	 * @return array{pay_instant: true, link: string} Payment redirect data.
	 *
	 * @throws Exception When payment link creation fails.
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onPaymentPay(string $context, object $order, array $links, Registry $params): array
	{
		return $this->getRadicalMartOrderPaymentLinkCreationService()
			->create($context, $order, $links);
	}

	/**
	 * Process a ModulBank callback and update the RadicalMart order.
	 *
	 * @param string                                                 $context Payment event context.
	 * @param array<string, mixed>                                   $input   Event input data.
	 * @param RadicalMartExpressPaymentModel|RadicalMartPaymentModel $model   RadicalMart payment model.
	 * @param Registry                                               $params  Component parameters.
	 *
	 * @return void
	 *
	 * @throws Exception When callback processing fails.
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onPaymentCallback(
		string                                                 $context,
		array                                                  $input,
		RadicalMartExpressPaymentModel|RadicalMartPaymentModel $model,
		Registry                                               $params
	): void
	{
		$app      = $this->getApplication();
		$appInput = $app->getInput();
		$postData = $appInput->post->getArray();

		$this->getRadicalMartOrderPaymentCallbackService()
			->handle(
				$context,
				$postData,
				$model
			);

		$app->close(200);
	}

	/**
	 * Set the callback URL in a RadicalMart method form.
	 *
	 * @param Form  $form    RadicalMart method form.
	 * @param mixed $data    Associated form data.
	 * @param mixed $tmpData Temporary form data.
	 *
	 * @return void
	 *
	 * @throws Exception When component parameters cannot be loaded.
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onRadicalMartPrepareMethodForm(Form $form, mixed $data = [], mixed $tmpData = []): void
	{
		$uri = Uri::getInstance();
		$radicalMartComponentParams = RadicalMartParamsHelper::getComponentParams();
		$value = $uri->toString(['scheme', 'host', 'port'])
			. '/' . $radicalMartComponentParams->get('payment_entry', 'radicalmart_payment')
			. '/wishboxmodulbank/callback';
		$form->setFieldAttribute('url_notify', 'default', $value, 'params');
	}

	/**
	 * Set the callback URL in a RadicalMart Express configuration form.
	 *
	 * @param Form  $form RadicalMart Express form.
	 * @param mixed $data Associated form data.
	 *
	 * @return void
	 *
	 * @throws Exception When component parameters cannot be loaded.
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 * @noinspection PhpUnusedParameterInspection
	 */
	public function onRadicalMartExpressPrepareConfigForm(Form $form, mixed $data = []): void
	{
		$uri = Uri::getInstance();
		$radicalMartExpressComponentParams = RadicalMartExpressParamsHelper::getComponentParams();
		$value = $uri->toString(['scheme', 'host', 'port'])
			. '/' . $radicalMartExpressComponentParams->get('payment_entry', 'radicalmart_express_payment')
			. '/callback';
		$form->setFieldAttribute('url_notify', 'default', $value, 'payment_method_params');
	}
}
