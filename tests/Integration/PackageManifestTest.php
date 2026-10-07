<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

/**
 * Verifies the installable package composition.
 *
 * @since 1.0.0
 */
final class PackageManifestTest extends TestCase
{
	/**
	 * Ensure the package contains only the ModulBank payment plugin.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testPackageContainsOnlyModulBankPaymentPlugin(): void
	{
		$projectRoot = dirname(__DIR__, 2);
		$manifest = simplexml_load_file($projectRoot . '/pkg_radicalmart_wishboxmodulbank.xml');

		self::assertInstanceOf(SimpleXMLElement::class, $manifest);
		self::assertSame('package', (string) $manifest['type']);
		self::assertSame('radicalmart_wishboxmodulbank', (string) $manifest->packagename);

		$extensionFiles = $manifest->xpath('files/file');

		self::assertIsArray($extensionFiles);
		self::assertCount(1, $extensionFiles);
		self::assertSame('plugin', (string) $extensionFiles[0]['type']);
		self::assertSame('radicalmart_payment', (string) $extensionFiles[0]['group']);
		self::assertSame('wishboxmodulbank', (string) $extensionFiles[0]['id']);
		self::assertSame(
			'plg_radicalmart_payment_wishboxmodulbank.zip',
			trim((string) $extensionFiles[0])
		);
		self::assertCount(0, $manifest->xpath('files/file[@type="library"]'));
	}

	/**
	 * Ensure service namespaces match the manifest namespace exactly.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testServiceProviderUsesManifestNamespaceCase(): void
	{
		$projectRoot = dirname(__DIR__, 2);
		$provider = file_get_contents(
			$projectRoot . '/plg_radicalmart_payment_wishboxmodulbank/services/provider.php'
		);

		self::assertIsString($provider);
		self::assertStringContainsString(
			'Joomla\\Plugin\\RadicalMartPayment\\WishboxModulBank\\Service',
			$provider
		);
		self::assertStringNotContainsString(
			'Joomla\\Plugin\\RadicalMartPayment\\WishboxModulbank\\Service',
			$provider
		);
	}

	/**
	 * Ensure installer requirements match the project and no remote code is installed.
	 *
	 * @return void
	 *
	 * @since 1.0.0
	 *
	 * @noinspection PhpUnused
	 */
	public function testInstallerRequiresPhp85AndDoesNotDownloadCode(): void
	{
		$projectRoot = dirname(__DIR__, 2);
		$installerScript = file_get_contents(
			$projectRoot . '/plg_radicalmart_payment_wishboxmodulbank/script.php'
		);

		self::assertIsString($installerScript);
		self::assertStringContainsString("MINIMUM_PHP = '8.5'", $installerScript);
		self::assertStringNotContainsString('file_get_contents(', $installerScript);
		self::assertStringNotContainsString('InstallerHelper::unpack', $installerScript);
	}
}
