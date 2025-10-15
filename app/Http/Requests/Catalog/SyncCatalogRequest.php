<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class SyncCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'products' => 'required|array|min:1|max:100', // Limit bulk operations
            'products.*.product_id' => 'required',
            'products.*.product_name' => 'required|string|max:255',
            'products.*.price' => 'required|numeric|min:0',
            'products.*.currency' => 'sometimes|string|size:3',
            'products.*.product_url' => 'sometimes|url|max:2048',
            'products.*.image_url' => 'sometimes|url|max:2048',
            'products.*.description' => 'sometimes|string|max:1000',
            'products.*.categories' => 'sometimes|array',
            'products.*.categories.*' => 'string|max:255',
            'products.*.sku' => 'sometimes|string|max:255',
            'products.*.custom_attributes' => 'sometimes|array',
        ];
    }

    public function messages(): array
    {
        return [
            'products.required' => 'Products array is required',
            'products.array' => 'Products must be an array',
            'products.min' => 'At least one product is required',
            'products.max' => 'Maximum 100 products allowed per bulk sync',
            'products.*.product_id.required' => 'Product ID is required for each product',
            'products.*.product_name.required' => 'Product name is required for each product',
            'products.*.product_name.string' => 'Product name must be a string',
            'products.*.price.required' => 'Price is required for each product',
            'products.*.price.numeric' => 'Price must be a number',
            'products.*.price.min' => 'Price cannot be negative',
            'products.*.currency.size' => 'Currency must be a 3-letter code (e.g., EUR, USD)',
            'products.*.product_url.url' => 'Product URL must be a valid URL',
            'products.*.image_url.url' => 'Image URL must be a valid URL',
            'products.*.categories.array' => 'Categories must be an array',
        ];
    }
}
