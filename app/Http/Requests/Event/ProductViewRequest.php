<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class ProductViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required',
            'product_name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'product_url' => 'sometimes|url|max:2048',
            'image_url' => 'sometimes|url|max:2048',
            'description' => 'sometimes|string|max:1000',
            'categories' => 'sometimes|array',
            'categories.*' => 'string|max:255',
            'sku' => 'sometimes|string|max:255',
            'custom_attributes' => 'sometimes|array',
            'customer' => 'sometimes|array',
            'customer.email' => 'required_with:customer|email',
            'customer.first_name' => 'sometimes|string|max:255',
            'customer.last_name' => 'sometimes|string|max:255',
            'customer.phone_number' => 'sometimes|string|max:50',
            'customer.properties' => 'sometimes|array',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Product ID is required',
            'product_name.required' => 'Product name is required',
            'product_name.string' => 'Product name must be a string',
            'price.required' => 'Product price is required',
            'price.numeric' => 'Product price must be a number',
            'price.min' => 'Product price cannot be negative',
            'currency.required' => 'Currency is required',
            'currency.size' => 'Currency must be a 3-letter code (e.g., EUR, USD)',
            'product_url.url' => 'Product URL must be a valid URL',
            'image_url.url' => 'Image URL must be a valid URL',
            'categories.array' => 'Categories must be an array',
            'customer.email.required_with' => 'Email is required when customer data is provided',
            'customer.email.email' => 'Customer email must be a valid email address',
        ];
    }
}
