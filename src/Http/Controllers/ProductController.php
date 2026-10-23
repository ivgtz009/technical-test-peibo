<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Money;
use App\Exceptions\ValidationException;
use App\Http\Request;
use App\Http\Response;
use App\Repository\ProductRepository;

final class ProductController
{
    public function __construct(private readonly ProductRepository $products)
    {
    }

    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params = []): Response
    {
        return Response::json($this->products->all());
    }

    /**
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params = []): Response
    {
        $errors = [];
        $name = $request->body['name'] ?? null;
        $price = $request->body['price'] ?? null;
        $stock = $request->body['stock'] ?? null;

        if (!is_string($name) || trim($name) === '') {
            $errors['name'] = 'Name is required';
        }

        $priceString = null;
        if (is_string($price) || is_int($price) || is_float($price)) {
            $priceString = is_string($price) ? $price : (string) $price;
            try {
                Money::fromDecimalString($priceString);
            } catch (\InvalidArgumentException) {
                $errors['price'] = 'Price must be a positive decimal with up to 2 decimals';
            }
        } else {
            $errors['price'] = 'Price is required';
        }

        if (!is_int($stock) || $stock < 0) {
            $errors['stock'] = 'Stock must be a non-negative integer';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        /** @var string $name */
        /** @var string $priceString */
        /** @var int $stock */
        $id = $this->products->create(
            trim($name),
            Money::fromDecimalString($priceString)->toDecimalString(),
            $stock,
        );

        return Response::json(['id' => $id], 201);
    }
}
