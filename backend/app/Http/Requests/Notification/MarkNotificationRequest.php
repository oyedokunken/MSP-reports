<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;

class MarkNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $id = $this->route('id');
        return $this->user()->notifications()->where('id', $id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
