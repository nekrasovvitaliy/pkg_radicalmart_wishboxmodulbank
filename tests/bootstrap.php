<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

// phpcs:disable PSR1.Files.SideEffects
defined('_JEXEC') or define('_JEXEC', 1);
// phpcs:enable PSR1.Files.SideEffects

$projectRoot = dirname(__DIR__);
$composerAutoload = $projectRoot . '/vendor/autoload.php';

if (is_file($composerAutoload))
{
	require_once $composerAutoload;
}

if (class_exists(Dotenv\Dotenv::class))
{
	Dotenv\Dotenv::createImmutable($projectRoot)->safeLoad();
}

spl_autoload_register(
	static function (string $className) use ($projectRoot): void {
		$namespacePrefix = 'Joomla\\Plugin\\RadicalMartPayment\\WishboxModulBank\\';

		if (!str_starts_with($className, $namespacePrefix))
		{
			return;
		}

		$relativeClassName = substr($className, strlen($namespacePrefix));
		$classFile = $projectRoot . '/plg_radicalmart_payment_wishboxmodulbank/src/'
			. str_replace('\\', '/', $relativeClassName) . '.php';

		if (is_file($classFile))
		{
			require_once $classFile;
		}
	}
);
