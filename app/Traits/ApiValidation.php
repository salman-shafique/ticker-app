<?php

namespace App\Traits;

/**
 * Trait ApiValidation.
 */
trait ApiValidation
{
    public function validateLogin()
    {
        $validator = validator(request()->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            $this->setResponseData("validation error", $validator->errors()->toArray(), 403);
            return false;
        }
        return true;
    }

    public function validateRegister()
    {
        $validator = validator(request()->all(), [
            'name' => ['nullable', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        if ($validator->fails()) {
            $this->setResponseData("validation error", $validator->errors()->toArray(), 403);

            return false;
        }

        return true;
    }

}
