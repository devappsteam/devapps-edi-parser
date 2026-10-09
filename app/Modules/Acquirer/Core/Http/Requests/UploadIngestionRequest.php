<?php

namespace App\Modules\Acquirer\Core\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadIngestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // For a production app this would validate the Bearer token or User
        return true;
    }

    public function rules(): array
    {
        $maxSize = config('modules.core.upload.max_file_size', 104857600) / 1024; // KB
        $allowedAcquirers = implode(',', config('modules.core.supported_acquirers', ['ALELO']));

        return [
            'acquirer' => ['required', 'string', "in:{$allowedAcquirers}"],
            'file' => [
                'required',
                'file',
                "max:{$maxSize}",
                // We don't restrict mimes heavily as EDI files often come as .txt, .rem, or no extension
            ],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ];
    }
}
