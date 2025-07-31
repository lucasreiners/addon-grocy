<?php

use Grocy\Helpers\BaseBarcodeLookupPlugin;
use GuzzleHttp\Client;

/*
	To use this plugin, configure it in data/config.php like this:
	Setting('STOCK_BARCODE_LOOKUP_PLUGIN', 'OpenFoodFactsBarcodeLookupPlugin');
*/

class OpenFoodFactsBarcodeLookupPlugin extends BaseBarcodeLookupPlugin
{
	public const PLUGIN_NAME = 'Open Food Facts';

	protected function ExecuteLookup($barcode)
	{
		// Take the preset user setting or otherwise simply the first existing location
		$locationId = $this->Locations[0]->id;
		if ($this->UserSettings['product_presets_location_id'] != -1) {
			$locationId = $this->UserSettings['product_presets_location_id'];
		}

		// Take the preset user setting or otherwise simply the first existing quantity unit
		$quId = $this->QuantityUnits[0]->id;
		if ($this->UserSettings['product_presets_qu_id'] != -1) {
			$quId = $this->UserSettings['product_presets_qu_id'];
		}

		$result = $this->lookupCustomProductProxy($barcode);
		if ($result !== null) {
			return [
				'name' => $result['name'],
				'location_id' => $locationId,
				'qu_id_purchase' => $quId,
				'qu_id_stock' => $quId,
				'__qu_factor_purchase_to_stock' => 1,
				'__barcode' => $barcode,
				'__image_url' => $result['image_url']
			];
		}

		$result = $this->lookupOpenFoodFacts($barcode);
		if ($result !== null) {
			return [
				'name' => $result['name'],
				'location_id' => $locationId,
				'qu_id_purchase' => $quId,
				'qu_id_stock' => $quId,
				'__qu_factor_purchase_to_stock' => 1,
				'__barcode' => $barcode,
				'__image_url' => $result['image_url']
			];
		}

		return [
			'name' => '[AutoImportFailed]_' . $barcode,
			'location_id' => $locationId,
			'qu_id_purchase' => $quId,
			'qu_id_stock' => $quId,
			'__qu_factor_purchase_to_stock' => 1,
			'__barcode' => $barcode,
			'__image_url' => $result['image_url']
		];
	}

	private function lookupOpenFoodFacts($barcode)
	{
		$productNameFieldLocalized = 'product_name_' . substr(GROCY_LOCALE, 0, 2);

		$webClient = new Client(['http_errors' => false]);
		$response = $webClient->request(
			'GET',
			'https://world.openfoodfacts.org/api/v2/product/' . preg_replace('/[^0-9]/', '', $barcode) . '?fields=product_name,image_url,brands,' . $productNameFieldLocalized,
			['headers' => ['User-Agent' => 'GrocyOpenFoodFactsBarcodeLookupPlugin/1.0 (https://grocy.info)']]
		);
		$statusCode = $response->getStatusCode();

		$data = json_decode($response->getBody());

		if ($statusCode == 404 || $data->status != 1) {
			return null;
		} else {
			$imageUrl = '';
			if (isset($data->product->image_url) && !empty($data->product->image_url)) {
				$imageUrl = $data->product->image_url;
			}

			$name = $data->product->product_name;
			if (isset($data->name) && !empty($data->name)) {
				$name = $data->name;
			}

			$brands = explode(', ', $data->product->brands);
			$brand = $brands[0] ?? '';

			$name = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß ]/', '', $name);
			$brand = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß ]/', '', $brand);

			return [
				'name' => '[AutoImportGrocy] ' . implode(' - ', array_filter([$brand, $name])),
				'image_url' => $imageUrl
			];
		}
	}

	private function lookupCustomProductProxy($barcode)
	{
		$webClient = new Client(['http_errors' => false]);
		$response = $webClient->request(
			'GET',
			'http://172.16.51.20:14000/api/lookup/' . preg_replace('/[^0-9]/', '', $barcode),
			['headers' => ['User-Agent' => 'GrocyOpenFoodFactsBarcodeLookupPlugin/1.0 (https://grocy.info)']]
		);
		$statusCode = $response->getStatusCode();

		$data = json_decode($response->getBody());

		if ($statusCode == 404 || !isset($data->name)) {
			return null;
		} else {
			$imageUrl = '';
			if (isset($data->productUrl) && !empty($data->productUrl)) {
				$imageUrl = $data->productUrl;
			}

			$name = $data->product->product_name;
			if (isset($data->name) && !empty($data->name)) {
				$name = $data->name;
			}

			$name = preg_replace('/[^a-zA-Z0-9äöüÄÖÜß ]/', '', $name);

			return [
				'name' => '[AutoImportProxy] ' . $name,
				'image_url' => $imageUrl
			];
		}
	}
}
