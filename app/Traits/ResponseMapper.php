<?php

namespace App\Traits;

trait ResponseMapper
{

    // Holds the value of error
    protected $error = null;

    // Hold the data to be sent in case of success
    protected $payload = null;
    protected $message = null;
    protected $responseCode = null;
    protected $errorCode = null;

    /**
     * @param $data
     * Sends response to the request.
     * @return array
     *
     */
    public function setPagination($data)
    {
        return [
            "page" => $data->currentPage(),
            "pageSize" => $data->perPage(),
            "totalPage" => (int)($data->total() / $data->perPage()) + 1,
            "totalRecords" => $data->total(),
        ];
    }

    public function setResponseData($payload = [], $responseCode = null)
    {
        $this->payload = $payload;
        $this->responseCode = $responseCode;
    }

    public function setErrorResponseData($errorCode = null, $message = null, $responseCode = null)
    {
        $this->payload = ['error' => (int)$errorCode, "error_message" => $message, 'timestamp' => now()->timestamp];
        $this->responseCode = $responseCode;
    }

    public function sendJsonResponse()
    {
        $this->responseCode = empty($this->responseCode) ? 200 : $this->responseCode;
        return response()->json($this->payload, $this->responseCode);
    }
}
