<?php

namespace App\Services;

use App\User;

class UserService extends BaseService
{
    /**
     * UserService constructor.
     *
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->model = $user;
    }

    /**
     * Generates the access token the current user.
     *
     * @param User $user
     * @param string $token
     * @return array
     */
    public function getAccessToken(User $user, $token = null): array
    {
        return [
            'access_token' => empty($token) ? auth()->login($user) : $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => $user,
        ];
    }

    /**
     * Create the User
     *
     * @param array $data
     * @return User
     */
    public function registerUser(array $data = []): User
    {
        return $this->model->create($data);
    }

}
