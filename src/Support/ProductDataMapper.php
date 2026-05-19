<?php

namespace App\Support;

use App\Entity\Box;
use App\Entity\Eliquid;
use App\Entity\Product;
use App\Entity\ProductImage;
use App\Entity\ProductVariant;

final class ProductDataMapper
{
    public static function toArray(Product $product): array
    {
        $images = array_map(
            static fn (ProductImage $image) => [
                'id' => $image->getId(),
                'url' => $image->getUrl(),
                'altText' => $image->getAltText(),
                'position' => $image->getPosition(),
                'isPrimary' => $image->isPrimary(),
            ],
            $product->getImages()->toArray()
        );

        $variants = array_map(
            static fn (ProductVariant $variant) => [
                'id' => $variant->getId(),
                'sku' => $variant->getSku(),
                'title' => $variant->getTitle(),
                'attributes' => $variant->getAttributes() ?? [],
                'price' => $variant->getPrice(),
                'quantity' => $variant->getQuantity(),
                'isDefault' => $variant->isDefault(),
            ],
            $product->getVariants()->toArray()
        );

        $categories = array_map(
            static fn ($category) => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'slug' => $category->getSlug(),
            ],
            $product->getCategories()->toArray()
        );

        $data = [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'slug' => $product->getSlug(),
            'quantity' => $product->getQuantity(),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'salePrice' => $product->getSalePrice(),
            'isActive' => $product->isActive(),
            'seenCount' => $product->getSeenCount(),
            'type' => self::resolveType($product),
            'brand' => $product->getBrand() ? [
                'id' => $product->getBrand()->getId(),
                'name' => $product->getBrand()->getName(),
            ] : null,
            'categories' => $categories,
            'images' => $images,
            'variants' => $variants,
            'createdAt' => $product->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $product->getUpdatedAt()?->format(DATE_ATOM),
        ];

        if ($product instanceof Box) {
            $data['type_battery'] = $product->getTypeBattery();
        }

        if ($product instanceof Eliquid) {
            $data['volume'] = $product->getVolume();
        }

        return $data;
    }

    public static function resolveType(Product $product): string
    {
        if ($product instanceof Box) {
            return 'box';
        }

        if ($product instanceof Eliquid) {
            return 'e-liquid';
        }

        return 'product';
    }
}
