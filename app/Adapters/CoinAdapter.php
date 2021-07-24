<?php

namespace App\Adapters;

use Exception;
use Illuminate\Support\Facades\Log;
use Ramsey\Collection\Collection;

class CoinAdapter
{

    public $base_url = "https://api.alternative.me/v2/";

    /**
     * Returns the coins list from cache
     * If Cache is empty, it fetches from the API and stores the response in cache.
     * @param string $sort
     * @return \Illuminate\Support\Collection
     *
     */
    public function getCoins($sort = null)
    {

        $coins = cache()->remember('coins', now()->addMonths(3), function () {
            try {
                $coins = [];
                $rawJson = file_get_contents($this->base_url . 'listings/');
                $coinsJson = json_decode($rawJson, true);
                foreach ($coinsJson['data'] as $coin) {
                    array_push($coins, ['code' => $coin['symbol'], 'name' => $coin['name'],]);
                }
            } catch (Exception $exception) {
                Log::error($exception->getMessage());
                return [];
            }
            return $coins;
        });
        $coinsCollection = collect($coins);
        if ($coinsCollection->isNotEmpty()) {
            $sorted = strtolower($sort) === 'desc' ? $coinsCollection->sortByDesc('name') : $coinsCollection->sortBy('name');
            $coinsCollection = $sorted->values()->all();
        }
        return $coinsCollection;
    }

    /**
     * Returns the ticker detail of a coin
     * If Cache is empty, it fetches from the API and stores the response in cache.
     * @param string $coinCode
     * @return array
     *
     */
    public function getCoin($coinCode)
    {
        $data = cache()->remember("coin_{$coinCode}", now()->addMinutes(5), function () use ($coinCode) {
            try {
                $tickerDetail = [];
                $rawJson = file_get_contents($this->base_url . 'ticker/' . $coinCode . '/');
                $coinJson = json_decode($rawJson, true);
                if (empty($coinJson['data'])) {
                    return ["error" => true, "message" => "Coin not found"];
                }
                foreach ($coinJson['data'] as $id => $coinData) {
                    $tickerDetail['code'] = $coinData['symbol'];
                    $tickerDetail['price'] = $coinData['quotes']['USD']['price'];
                    $tickerDetail['volume'] = $coinData['quotes']['USD']['volume_24h'];
                    $tickerDetail['daily_change'] = $coinData['quotes']['USD']['percentage_change_24h'];
                    $tickerDetail['last_updated'] = $coinData;
                    $tickerDetail['last_updated'] = now()->timestamp;
                    break;
                }
            } catch (Exception $exception) {
                Log::error($exception->getMessage());
                return ["error" => true, "message" => $exception->getMessage()];
            }
            return $tickerDetail;
        });
        return $data;
    }
}
