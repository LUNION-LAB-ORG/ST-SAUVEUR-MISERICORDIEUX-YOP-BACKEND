<?php

namespace App\Http\Requests\ChurchProject;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * En multipart, `phases` peut arriver sous forme de chaîne JSON : on la décode.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('phases'))) {
            $decoded = json_decode($this->input('phases'), true);
            if (is_array($decoded)) {
                $this->merge(['phases' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'title'             => 'sometimes|string|max:255',
            'presentation'      => 'sometimes|nullable|string',
            'goal_amount'       => 'sometimes|integer|min:0',
            'adjustment_amount' => 'sometimes|integer',
            'phases'            => 'sometimes|array',
            'phases.*.name'     => 'required_with:phases|string|max:150',
            'phases.*.status'   => 'required_with:phases|string|in:done,in_progress,upcoming',
            'image'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }
}
