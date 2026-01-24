<?php

namespace App\Repositories\User;

use App\Models\User;

class UserRepository
{
    protected User $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    function create(array $data): User
    {
        return $this->model->create($data);
    }

    // register-related methods will go here
}
