<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Event\DispatcherInterface;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Extension\WishboxModulBank;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\ModulBankCallbackValidator;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\ModulBankPaymentRequestFactory;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\ModulBankSignatureService;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\PaymentLinkCreationService;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\PaymentMethodParamsResolver;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\PaymentMethodParamsResolverAwareInterface;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\RadicalMartOrderPaymentCallbackService;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\RadicalMartOrderPaymentCallbackServiceAwareInterface;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\RadicalMartOrderPaymentLinkCreationService;
use Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Service\RadicalMartOrderPaymentLinkCreationServiceAwareInterface;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Provides the WishBox ModulBank payment plugin dependencies.
 *
 * @since 1.0.0
 */
return new class implements ServiceProviderInterface
{
	/**
	 * Register the plugin dependencies.
	 *
	 * @param Container $container Dependency injection container.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function register(Container $container): void
	{
		$container->set(
			ModulBankSignatureService::class,
			static fn (): ModulBankSignatureService => new ModulBankSignatureService
		);

		$container->set(
			PaymentMethodParamsResolver::class,
			static fn (): PaymentMethodParamsResolver => new PaymentMethodParamsResolver
		);

		$container->set(
			PaymentLinkCreationService::class,
			static fn (): PaymentLinkCreationService => new PaymentLinkCreationService
		);

		$container->set(
			ModulBankPaymentRequestFactory::class,
			static fn (Container $container): ModulBankPaymentRequestFactory => new ModulBankPaymentRequestFactory(
				$container->get(ModulBankSignatureService::class),
				$container->get(PaymentMethodParamsResolver::class)
			)
		);

		$container->set(
			ModulBankCallbackValidator::class,
			static fn (Container $container): ModulBankCallbackValidator => new ModulBankCallbackValidator(
				$container->get(ModulBankSignatureService::class),
				$container->get(PaymentMethodParamsResolver::class)
			)
		);

		$container->set(
			RadicalMartOrderPaymentLinkCreationService::class,
			static fn (Container $container): RadicalMartOrderPaymentLinkCreationService =>
				new RadicalMartOrderPaymentLinkCreationService(
					$container->get(PaymentLinkCreationService::class),
					$container->get(ModulBankPaymentRequestFactory::class),
					$container->get(PaymentMethodParamsResolver::class)
				)
		);

		$container->set(
			RadicalMartOrderPaymentCallbackService::class,
			static fn (Container $container): RadicalMartOrderPaymentCallbackService =>
				new RadicalMartOrderPaymentCallbackService(
					$container->get(ModulBankCallbackValidator::class),
					$container->get(PaymentMethodParamsResolver::class)
				)
		);

		$container->set(
			PluginInterface::class,
			static function (Container $container): PluginInterface {
				$subject = $container->get(DispatcherInterface::class);
				$config  = (array) PluginHelper::getPlugin('radicalmart_payment', 'wishboxmodulbank');
				$plugin  = new WishboxModulBank($subject, $config);

				$app = Factory::getApplication();
				$plugin->setApplication($app);

				if ($plugin instanceof PaymentMethodParamsResolverAwareInterface)
				{
					$plugin->setPaymentMethodParamsResolver(
						$container->get(PaymentMethodParamsResolver::class)
					);
				}

				if ($plugin instanceof RadicalMartOrderPaymentLinkCreationServiceAwareInterface)
				{
					$plugin->setRadicalMartOrderPaymentLinkCreationService(
						$container->get(RadicalMartOrderPaymentLinkCreationService::class)
					);
				}

				if ($plugin instanceof RadicalMartOrderPaymentCallbackServiceAwareInterface)
				{
					$plugin->setRadicalMartOrderPaymentCallbackService(
						$container->get(RadicalMartOrderPaymentCallbackService::class)
					);
				}

				return $plugin;
			}
		);
	}
};
