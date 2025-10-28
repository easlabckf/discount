<?php

class DiscountEngine
{
    public const BOOK_VIP_THRESHOLD = 20;
    public const BOOK_NONVIP_THRESHOLD = 50;
    public const NONBOOK_VIP_THRESHOLD = 100;

    public const BOOK_VIP_HIGH_DISCOUNT = 0.85;
    public const BOOK_VIP_LOW_DISCOUNT = 0.90;
    public const BOOK_NONVIP_DISCOUNT = 0.95;
    public const NONBOOK_VIP_DISCOUNT = 0.90;

    public const CART_DISCOUNT_HIGH_THRESHOLD = 500;
    public const CART_DISCOUNT_LOW_THRESHOLD = 200;

    public const CART_DISCOUNT_HIGH_AMOUNT = 25;
    public const CART_DISCOUNT_LOW_AMOUNT = 10;

    private array $itemRules = [];

    private array $cartRules = [];

    public function addItemRule(callable $rule): self
    {
        $this->itemRules[] = $rule;
        return $this;
    }

    public function addCartRule(callable $rule): self
    {
        $this->cartRules[] = $rule;
        return $this;
    }

    public function calculate(array $items, array $user): float
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $finalItemPrice = null;

            foreach ($this->itemRules as $rule) {
                $price = $rule($item, $user);
                if ($price !== null) {
                    $finalItemPrice = $price;
                    break;
                }
            }

            $subtotal += $finalItemPrice ?? (float) $item['price'];
        }

        $total = $subtotal;
        foreach ($this->cartRules as $rule) {
            $total = $rule($total, $user);
        }

        return $total;
    }
}

function calculateDiscount($items, $user): float
{
    $engine = (new DiscountEngine())
        ->addItemRule(function (array $item, array $user): ?float {
            if (($item['type'] ?? null) !== 'book' || empty($user['vip'])) {
                return null;
            }
            $price = (float)$item['price'];
            return $price > DiscountEngine::BOOK_VIP_THRESHOLD
                ? $price * DiscountEngine::BOOK_VIP_HIGH_DISCOUNT
                : $price * DiscountEngine::BOOK_VIP_LOW_DISCOUNT;
        })
        ->addItemRule(function (array $item, array $user): ?float {
            if (($item['type'] ?? null) !== 'book' || !empty($user['vip'])) {
                return null;
            }
            $price = (float)$item['price'];
            return $price > DiscountEngine::BOOK_NONVIP_THRESHOLD
                ? $price * DiscountEngine::BOOK_NONVIP_DISCOUNT
                : $price;
        })
        ->addItemRule(function (array $item, array $user): ?float {
            if (($item['type'] ?? null) === 'book' || empty($user['vip'])) {
                return null;
            }
            $price = (float)$item['price'];
            return $price > DiscountEngine::NONBOOK_VIP_THRESHOLD
                ? $price * DiscountEngine::NONBOOK_VIP_DISCOUNT
                : $price;
        })
        ->addCartRule(function (float $sum, array $user): float {
            return $sum > DiscountEngine::CART_DISCOUNT_HIGH_THRESHOLD
                ? $sum - DiscountEngine::CART_DISCOUNT_HIGH_AMOUNT
                : $sum;
        })
        ->addCartRule(function (float $sum, array $user): float {
            return ($sum > DiscountEngine::CART_DISCOUNT_LOW_THRESHOLD && empty($user['vip']))
                ? $sum - DiscountEngine::CART_DISCOUNT_LOW_AMOUNT
                : $sum;
        });

    return $engine->calculate($items, $user);
}
