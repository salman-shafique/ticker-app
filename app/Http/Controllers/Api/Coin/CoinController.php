<?php

namespace App\Http\Controllers\Api\Coin;

use App\Adapters\CoinAdapter;
use App\Http\Controllers\Controller;
use App\Traits\ApiValidation;
use App\Traits\ResponseMapper;
use Exception;
use Illuminate\Http\Request;

class CoinController extends Controller
{
    use  ResponseMapper, ApiValidation;

    protected $coinAdapter;

    public function __construct(CoinAdapter $coinAdapter)
    {
        $this->coinAdapter = $coinAdapter;
    }

    /**
     * @OA\GET(path="/api/v1/coins",
     *   tags={"Coins"},
     *   summary="Coins list",
     *   @OA\Parameter(
     *          name="sort",
     *          description="Sort param: Values are ASC|DESC",
     *          required=false,
     *          in="query",
     *          @OA\Schema(
     *              type="string"
     *          )
     *   ),
     *   description="Returns coins list",
     *   @OA\Response(response=400, description="Bad request", @OA\JsonContent()),
     *   @OA\Response(response=500, description="Eexception", @OA\JsonContent()),
     *   @OA\Response(response=200, description="successful operation", @OA\JsonContent())
     * )
     */
    public function index(Request $request)
    {
        try {
            $coins = $this->coinAdapter->getCoins($request->sort);
            $this->payload = $coins;
        } catch (Exception $exception) {
            $this->setErrorResponseData(500, $exception->getMessage(), 500);
        } finally {
            return $this->sendJsonResponse();
        }
    }

    /**
     * @OA\GET(path="/api/v1/ticker/{coin_code}",
     *   tags={"Coins"},
     *   summary="Coins list",
     *   security={{"bearer_token":{}}},
     *   @OA\Parameter(
     *          name="coin_code",
     *          description="Coin code",
     *          required=true,
     *          in="path",
     *          @OA\Schema(
     *              type="string"
     *          )
     *   ),
     *   description="Returns coins list",
     *   @OA\Response(response=400, description="Bad request", @OA\JsonContent()),
     *   @OA\Response(response=500, description="Eexception", @OA\JsonContent()),
     *   @OA\Response(response=200, description="successful operation", @OA\JsonContent())
     * )
     */
    public function ticker($coinCode)
    {
        try {
            $coin = $this->coinAdapter->getCoin($coinCode);
            if (isset($coin['error'])) {
                $this->setErrorResponseData(__('validation.error_code.coin_not_found'), $coin['message'], 404);
            } else {
                $this->setResponseData($coin, 200);
            }
        } catch (Exception $exception) {
            $this->setErrorResponseData(500, $exception->getMessage(), 500);
        } finally {
            return $this->sendJsonResponse();
        }
    }
}
