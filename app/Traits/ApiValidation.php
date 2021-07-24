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
            $this->setErrorResponseData(__("validation.error_code.{$validator->errors()->keys()[0]}" ?? 0), $validator->errors()->first(), 422);
            return false;
        }
        return true;
    }

    public function validateRegister()
    {
        $validator = validator(request()->all(), [
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);
        if ($validator->fails()) {
            $this->setErrorResponseData(__("validation.error_code.{$validator->errors()->keys()[0]}" ?? 0), $validator->errors()->first(), 422);

            return false;
        }

        return true;
    }

}
