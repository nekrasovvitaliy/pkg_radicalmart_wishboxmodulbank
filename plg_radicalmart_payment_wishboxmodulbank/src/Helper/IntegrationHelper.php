<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license         GNU General Public License version 2 or later;
 */
namespace Joomla\Plugin\RadicalMartPayment\WishboxModulBank\Helper;

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Exception;
use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\MVC\Model\ModelInterface;
use Joomla\Component\RadicalMart\Administrator\Helper\DebugHelper as RadicalMartDebugHelper;
use Joomla\Component\RadicalMart\Administrator\Helper\ParamsHelper as RadicalMartParamsHelper;
use Joomla\Component\RadicalMart\Administrator\Model\OrderModel as RadicalMartOrderModel;
use Joomla\Component\RadicalMartExpress\Administrator\Helper\DebugHelper as RadicalMartExpressDebugHelper;
use Joomla\Component\RadicalMartExpress\Administrator\Helper\ParamsHelper as RadicalMartExpressParamsHelper;
use Joomla\Component\RadicalMartExpress\Administrator\Model\OrderModel as RadicalMartExpressOrderModel;
use Joomla\Registry\Registry;
use function defined;

/**
 * @since 1.0.0
 */
class IntegrationHelper
{
	public const string RadicalMart = 'com_radicalmart';
	public const string RadicalMartExpress = 'com_radicalmart_express';

	/**
	 * Loggers initials cache.
	 *
	 * @var array
	 *
	 * @since 1.0.0
	 */
	protected static array $_loggers = [];

	/**
	 * Method to get component from form context.
	 *
	 * @param   string|null  $context  Context selector string.
	 *
	 * @return string|bool Component name on success, False on failure.
	 *
	 * @since 1.0.0
	 */
	public static function getComponentFromContext(?string $context = null): string|bool
	{
		if (empty($context))
		{
			return false;
		}

		if (str_starts_with($context, 'com_radicalmart.'))
		{
			return self::RadicalMart;
		}

		if (str_starts_with($context, 'com_radicalmart_express.'))
		{
			return self::RadicalMartExpress;
		}

		return false;
	}

	/**
	 * Method to get DebugHelper class name.
	 *
	 * @param   string  $component  Component name.
	 *
	 * @return bool|string Helper class name on success, False on failure.
	 *
	 * @since 1.0.0
	 */
	public static function getDebugHelper(string $component): bool|string
	{
		if ($component === self::RadicalMart)
		{
			return RadicalMartDebugHelper::class;
		}

		if ($component === self::RadicalMartExpress)
		{
			return RadicalMartExpressDebugHelper::class;
		}

		return false;
	}

	/**
	 * Method to get ParamsHelper class name.
	 *
	 * @param   string  $component  Component name.
	 *
	 * @return bool|string Helper class name on success, False on failure.
	 *
	 * @since 1.0.0
	 */
	public static function getParamsHelper(string $component): bool|string
	{
		if ($component === self::RadicalMart)
		{
			return RadicalMartParamsHelper::class;
		}

		if ($component === self::RadicalMartExpress)
		{
			return RadicalMartExpressParamsHelper::class;
		}

		return false;
	}

	/**
	 * Get a model from the selected RadicalMart component.
	 *
	 * @param string               $component RadicalMart component name.
	 * @param string               $name      Model name.
	 * @param string               $prefix    Model client prefix.
	 * @param array<string, mixed> $config    Model creation configuration.
	 *
	 * @throws Exception
	 *
	 * @return ModelInterface|bool Component model instance or false for an unsupported component.
	 *
	 * @since 1.0.0
	 */
	public static function getModel(
		string $component,
		string $name,
		string $prefix = '',
		array  $config = ['ignore_request' => true]
	): bool|ModelInterface
	{
		/** @var SiteApplication|AdministratorApplication $app */
		$app = Factory::getApplication();

		if ($component === self::RadicalMart)
		{
			/** @var MVCComponent $radicalMartComponent */
			$radicalMartComponent = $app->bootComponent('com_radicalmart');
			$mvcFactory = $radicalMartComponent->getMVCFactory();
		}
		elseif ($component === self::RadicalMartExpress)
		{
			/** @var MVCComponent $radicalMartExpressComponent */
			$radicalMartExpressComponent = $app->bootComponent('com_radicalmart_express');
			$mvcFactory = $radicalMartExpressComponent->getMVCFactory();
		}
		else
		{
			return false;
		}

		return $mvcFactory->createModel($name, $prefix, $config);
	}

	/**
	 * Method to get Admin order model.
	 *
	 * @param   string  $component  Component name.
	 *
	 * @throws Exception
	 *
	 * @return bool|RadicalMartOrderModel|RadicalMartExpressOrderModel|null Admin order model on success, False or null on failure.
	 *
	 * @since 1.0.0
	 */
	public static function getOrderModel(string $component): bool|null|RadicalMartOrderModel|RadicalMartExpressOrderModel
	{
		return self::getModel($component, 'Order', 'Administrator');
	}

	/**
	 * Method to add order log.
	 *
	 * @param   string  $component  Component name.
	 * @param   int     $order_id   Order id.
	 * @param   string  $action     Action name.
	 * @param   array   $data       Log data.
	 *
	 * @throws Exception
	 *
	 * @since 1.0.0
	 */
	public static function addOrderLog(string $component, int $order_id, string $action, array $data): void
	{
		$model = self::getOrderModel($component);

		if (!$model)
		{
			return;
		}

		$model->addLog($order_id, $action, $data);
	}

	/**
	 * Method to log error.
	 *
	 * @param   string       $category  Log category name.
	 * @param   int          $priority  Message priority.
	 * @param   string|null  $message   Message text.
	 * @param   array        $data      Message advanced data.
	 * @param   int          $code      Message code.
	 *
	 * @since 1.0.0
	 */
	public static function addLog(string  $category, int $priority = Log::INFO,
	                              ?string $message = null, array $data = [], int $code = 0): void
	{
		if (!isset(self::$_loggers[$category]))
		{
			Log::addLogger([
				'text_file'         => $category . '.php',
				'text_entry_format' => "{DATETIME}\t{CLIENTIP}\t{MESSAGE}\t{PRIORITY}"],
				Log::ALL,
				[$category]
			);

			self::$_loggers[$category] = true;
		}

		if (!empty($data))
		{
			$entry = [];

			if (!empty($code))
			{
				$entry['code'] = $code;
			}

			if (!empty($message))
			{
				$entry['message'] = $message;
			}

			$entry['data'] = $data;

			$entry = new Registry($entry)->toString();
		}

		else
		{
			$entry = (!empty($message)) ? $message : '';
		}

		Log::add($entry, $priority, $category);
	}
}
