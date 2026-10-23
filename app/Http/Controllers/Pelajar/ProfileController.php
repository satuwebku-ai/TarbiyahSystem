<?php

namespace App\Http\Controllers\Pelajar;

use App\Http\Controllers\Auth\BaseProfileController;
use Illuminate\Validation\Rule;

class ProfileController extends BaseProfileController
{
    protected function guardName(): string { return 'pelajar'; }
    protected function profileView(): string { return 'pelajar.profile'; }

    /** NIS tidak bisa diubah sendiri oleh pelajar; email boleh kosong. */
    protected function profileRules($user): array
    {
        return [
            'name'  => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique($user->getTable(), 'email')->ignore($user->id)],
        ];
    }
}
