<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class DeleteNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('id');
        return $this->user()->notifications()->where('id', $id)->exists();
    }

    public function rules(): array
    {
        return [];
    }
}
