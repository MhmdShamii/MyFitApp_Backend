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

    function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    // register-related methods will go here
}
