<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class OrderPlacedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required',
            'order_number' => 'required|string|max:255',
            'total' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required',
            'items.*.product_name' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.sku' => 'sometimes|string|max:255',
            'subtotal' => 'sometimes|numeric|min:0',
            'tax' => 'sometimes|numeric|min:0',
            'shipping' => 'sometimes|numeric|min:0',
            'discount' => 'sometimes|numeric|min:0',
            'billing_address' => 'sometimes|array',
            'billing_address.first_name' => 'sometimes|string|max:255',
            'billing_address.last_name' => 'sometimes|string|max:255',
            'billing_address.company' => 'sometimes|string|max:255',
            'billing_address.address1' => 'sometimes|string|max:255',
            'billing_address.address2' => 'sometimes|string|max:255',
            'billing_address.city' => 'sometimes|string|max:255',
            'billing_address.region' => 'sometimes|string|max:255',
            'billing_address.postal_code' => 'sometimes|string|max:20',
            'billing_address.country' => 'sometimes|string|max:255',
            'shipping_address' => 'sometimes|array',
            'shipping_address.first_name' => 'sometimes|string|max:255',
            'shipping_address.last_name' => 'sometimes|string|max:255',
            'shipping_address.company' => 'sometimes|string|max:255',
            'shipping_address.address1' => 'sometimes|string|max:255',
            'shipping_address.address2' => 'sometimes|string|max:255',
            'shipping_address.city' => 'sometimes|string|max:255',
            'shipping_address.region' => 'sometimes|string|max:255',
            'shipping_address.postal_code' => 'sometimes|string|max:20',
            'shipping_address.country' => 'sometimes|string|max:255',
            'customer' => 'required|array',
            'customer.email' => 'required|email',
            'customer.first_name' => 'sometimes|string|max:255',
            'customer.last_name' => 'sometimes|string|max:255',
            'customer.phone_number' => 'sometimes|string|max:50',
            'customer.properties' => 'sometimes|array',
            'status' => 'sometimes|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => 'Order ID is required',
            'order_number.required' => 'Order number is required',
            'total.required' => 'Order total is required',
            'total.numeric' => 'Order total must be a number',
            'total.min' => 'Order total cannot be negative',
            'currency.required' => 'Currency is required',
            'currency.size' => 'Currency must be a 3-letter code (e.g., EUR, USD)',
            'items.required' => 'Order items are required',
            'items.array' => 'Order items must be an array',
            'items.min' => 'Order must contain at least one item',
            'items.*.product_id.required' => 'Product ID is required for each item',
            'items.*.product_name.required' => 'Product name is required for each item',
            'items.*.quantity.required' => 'Quantity is required for each item',
            'items.*.quantity.integer' => 'Quantity must be an integer',
            'items.*.quantity.min' => 'Quantity must be at least 1',
            'items.*.price.required' => 'Price is required for each item',
            'items.*.price.numeric' => 'Price must be a number',
            'customer.required' => 'Customer information is required',
            'customer.email.required' => 'Customer email is required',
            'customer.email.email' => 'Customer email must be a valid email address',
        ];
    }
}
