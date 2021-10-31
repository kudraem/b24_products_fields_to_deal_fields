<?php

require_once __DIR__ . '/bootstrap.php';

use Bitrix24\CRM\ProductRow;
use Bitrix24\CRM\Product;
use Bitrix24\CRM\Deal\UserField;
use Bitrix24\CRM\Deal\Deal;

$log->debug('Input request', array('request get query' => var_export($_GET, true)));

if (!isset($_GET['secret'])) {
    $log->error('Input request empty secret');
    die();
}

if ($_GET['secret'] != $_ENV['APP_SECRET']) {
    $log->error('Input request invalid secret');
    die();
}

if (!isset($_GET['deal_id'])) {
    $log->error('Input request empty deal_id');
    die();
}

$dealId = $_GET['deal_id'];

$docResponsibleMap = getFieldsMapping('PROPERTY_110', 'UF_CRM_1588932467667');
$dealProducts = getDealProducts($dealId);
$dealTotal = getDealTotal($dealId);

$dealFieldsToUpdate = [
	'UF_CRM_1633585311' => 0,
	'UF_CRM_1588932467667' => [],
	'UF_CRM_1635074504108' => 0,
	'UF_CRM_1635077150240' => '',
	'COMMENTS' => '',
];

foreach ($dealProducts as $product) {
	if (!is_null($product['purchasePrice'])) {
		$dealFieldsToUpdate['UF_CRM_1633585311'] += $product['purchasePrice'] * $product['qty'];
	}

	if (!is_null($product['docResponsible'])) {
		if (isset($docResponsibleMap[$product['docResponsible']])) {
			$docResponsibleDealFieldId = $docResponsibleMap[$product['docResponsible']];
			if (!in_array($docResponsibleDealFieldId, $dealFieldsToUpdate['UF_CRM_1588932467667'])) {
				$dealFieldsToUpdate['UF_CRM_1588932467667'][] = $docResponsibleDealFieldId;
			}
		}
	}

	if (!is_null($product['formatFile'])) {
		$dealFieldsToUpdate['UF_CRM_1635077150240'] .= $product['formatFile'] . "\n\n";
		$dealFieldsToUpdate['COMMENTS'] .= $product['formatFile'] . "\n\n";
	}
}

$dealFieldsToUpdate['UF_CRM_1635074504108'] = $dealTotal - $dealFieldsToUpdate['UF_CRM_1633585311'];

$log->debug('Deal fields to update', [
	'deal_id' => $dealId,
	'fields_to_update' => $dealFieldsToUpdate
]);

$obB24Deal = new Deal($obB24App);
$result = $obB24Deal->update($dealId, $dealFieldsToUpdate);

$log->debug('Deal update result', [
	'deal_id' => $dealId,
	'result' => $result
]);


function getDealProducts($dealId)
{
	global $obB24App;

	$dealProducts = [];

	$obB24ProductRow = new ProductRow($obB24App);

	$ownerType = 'D';
	$ownerId = $dealId;

	$productRowGetListResult = $obB24ProductRow->getList($ownerType, $ownerId);

	$obB24Product = new Product($obB24App);

	foreach ($productRowGetListResult['result'] as $value) {
		$productId = $value['PRODUCT_ID'];

		$product = [
			'productId' => $productId,
			'qty' => (int)$value['QUANTITY'],
			'docResponsible' => null,
			'formatFile' => null,
			'purchasePrice' => null
		];

		$productGetResult = $obB24Product->get($productId);

		if (!empty($productGetResult['result']['PROPERTY_110'])) {
			$product['docResponsible'] = $productGetResult['result']['PROPERTY_110']['value'];
		}

		if (!empty($productGetResult['result']['PROPERTY_144'])) {
			$product['formatFile'] = $productGetResult['result']['PROPERTY_144']['value']['TEXT'];
		}

		if (!empty($productGetResult['result']['PROPERTY_146'])) {
			$valueParts = explode('|', $productGetResult['result']['PROPERTY_146']['value']);

			$product['purchasePrice'] = $valueParts[0];
		}

		$dealProducts[] = $product;
	}

	return $dealProducts;
}

function getFieldsMapping($productCFId, $dealCFId)
{
	global $obB24App;

	$map = [];

	$dealFieldValuesList = [];

	$obB24DealUserField = new UserField($obB24App);
	$result = $obB24DealUserField->getList();

	foreach ($result['result'] as $field) {
		if ($field['FIELD_NAME'] == $dealCFId) {
			if (isset($field['LIST'])) {
				foreach ($field['LIST'] as $fieldValue) {
					$dealFieldValuesList[] = [
						'id' => $fieldValue['ID'],
						'value' => $fieldValue['VALUE']
					];
				}
			}
		}
	}

	$productFieldValuesList = [];

	$obB24Product = new Product($obB24App);
	$result = $obB24Product->fields();

	if (isset($result['result'][$productCFId])) {
		foreach ($result['result'][$productCFId]['values'] as $fieldValue) {
			$productFieldValuesList[] = [
				'id' => $fieldValue['ID'],
				'value' => $fieldValue['VALUE']
			];
		}
	}

	foreach ($productFieldValuesList as $productFieldValue) {
		foreach ($dealFieldValuesList as $dealFieldValue) {
			if ($productFieldValue['value'] == $dealFieldValue['value']) {
				$map[$productFieldValue['id']] = $dealFieldValue['id'];
				break;
			}
		}
	}

	return $map;
}

function getDealTotal($dealId)
{
	global $obB24App;

	$obB24Deal = new Deal($obB24App);
	$result = $obB24Deal->get($dealId);

	return $result['result']['OPPORTUNITY'];
}

