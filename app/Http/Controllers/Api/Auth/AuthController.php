<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Traits\ApiValidation;
use App\Traits\ResponseMapper;
use App\Services\UserService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    use  ResponseMapper, ApiValidation;

    protected $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * @OA\Post(path="/api/register",
     *   tags={"Authentication"},
     *   summary="Register user",
     *   description="This can only be done by guest user.",
     *   operationId="registerUser",
     *   @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     description="Required *"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     description="Required *"
     *                 ),
     *                  @OA\Property(
     *                     property="passowrd",
     *                     type="string",
     *                     description="Required * min:8"
     *                 )
     *             )
     *         )
     *   ),
     *   @OA\Response(response=400, description="Bad request"),
     *   @OA\Response(response=500, description="Eexception"),
     *   @OA\Response(response=200, description="successful operation")
     * )
     */
    public function register(Request $request)
    {
        try {
            $validated = $this->validateRegister();
            if ($validated !== true) {
                return;
            }
            $params = $request->all();
            $user = $this->userService->registerUser($params);
            $this->message = __('messages.register_success');
            $this->payload = $this->userService->getAccessToken($user);

        } catch (Exception $ex) {
            $this->setResponseData($ex->getMessage(), $ex->getMessage(), 500);
        } finally {
            return $this->sendJsonResponse();
        }
    }

    /**
     * @OA\Post(path="/api/login",
     *   tags={"Authentication"},
     *   summary="Login user",
     *   description="This can only be done by guest user.",
     *   operationId="loginUser",
     *   @OA\RequestBody(
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="email",
     *                     type="string"
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="string"
     *                 )
     *             )
     *         )
     *   ),
     *   @OA\Response(response=400, description="Bad request"),
     *   @OA\Response(response=500, description="Eexception"),
     *   @OA\Response(response=200, description="successful operation")
     * )
     */
    public function login(Request $request)
    {
        try {
            $validated = $this->validateLogin();
            if ($validated !== true) {
                return;
            }
            if ($token = auth()->attempt($request->only(['email', 'password']))) {
                $user = auth()->user();
                $this->payload = $this->userService->getAccessToken($user, $token);
                return;
            }
            $this->setResponseData(__('messages.invalid_login'), __('messages.invalid_login'), 403);
        } catch (Exception $exception) {
            $this->setResponseData($exception->getMessage(), $exception->getMessage(), 500);
        } finally {
            return $this->sendJsonResponse();
        }
    }


    public function self()
    {
        try {
            $this->payload = auth()->user();
        } catch (Exception $exception) {
            $this->setResponseData($exception->getMessage(), $exception->getMessage(), 500);
        } finally {
            return $this->sendJsonResponse();
        }
    }
}
