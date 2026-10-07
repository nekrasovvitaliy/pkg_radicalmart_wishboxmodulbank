<?php
/**
 * @copyright   (c) 2013-2026 Nekrasov Vitaliy <nekrasov_vitaliy@list.ru>
 * @license     GNU General Public License version 2 or later;
 */

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($path === '/success')
{
	parse_str(file_get_contents('php://input'), $requestData);
	header('Content-Type: application/json');
	echo json_encode([
		'status' => 'ok',
		'bill'   => [
			'url' => 'https://pay.example.test/bill/' . ($requestData['order_id'] ?? 'unknown'),
		],
	], JSON_THROW_ON_ERROR);

	return;
}

if ($path === '/rejected')
{
	header('Content-Type: application/json');
	echo json_encode([
		'status'  => 'error',
		'message' => 'Payment request rejected by test server.',
	], JSON_THROW_ON_ERROR);

	return;
}

if ($path === '/unauthorized')
{
	http_response_code(401);
	header('Content-Type: application/json');
	echo json_encode([
		'status'  => 'error',
		'message' => 'Криптографическая подпись: Неверное значение поля signature',
	], JSON_THROW_ON_ERROR);

	return;
}

if ($path === '/invalid-json')
{
	header('Content-Type: application/json');
	echo '{invalid';

	return;
}

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['status' => 'error'], JSON_THROW_ON_ERROR);
