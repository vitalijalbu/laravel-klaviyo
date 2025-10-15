<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class TrackEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'event' => 'required|string|max:255',
            'properties' => 'required|array',
            'customer' => 'sometimes|array',
            'customer.email' => 'required_with:customer|email',
            'customer.first_name' => 'sometimes|string|max:255',
            'customer.last_name' => 'sometimes|string|max:255',
            'customer.phone_number' => 'sometimes|string|max:50',
            'customer.properties' => 'sometimes|array',
            'unique_id' => 'sometimes|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'event.required' => 'Event name is required',
            'event.string' => 'Event name must be a string',
            'properties.required' => 'Event properties are required',
            'properties.array' => 'Event properties must be an array',
            'customer.email.required_with' => 'Email is required when customer data is provided',
            'customer.email.email' => 'Customer email must be a valid email address',
        ];
    }
}
