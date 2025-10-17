<?php

declare(strict_types=1);

namespace JTL\RMA\DomainObjects;

use JTL\Catalog\Product\Artikel;
use JTL\DataObjects\AbstractDomainObject;

/**
 * Class RMAItemDomainObject
 * @package JTL\RMA
 * @description DTO for holding rma items or returnable products
 * @comment The public properties represent the database table columns
 */
class RMAItemDomainObject extends AbstractDomainObject
{
    public readonly int $id;
    public readonly int $rmaID;
    public readonly ?int $shippingNotePosID;
    public readonly ?int $orderID;
    public readonly ?int $orderPosID;
    public readonly ?int $productID;
    public readonly ?int $reasonID;
    public readonly string $name;
    public readonly ?int $variationProductID;
    public readonly ?string $variationName;
    public readonly ?string $variationValue;
    public readonly ?int $partListProductID;
    public readonly ?string $partListProductName;
    public readonly ?string $partListProductURL;
    public readonly ?string $partListProductNo;
    public readonly float $unitPriceNet;
    public readonly float $quantity;
    public readonly float $vat;
    public readonly ?string $unit;
    public readonly ?string $comment;
    public readonly ?string $status;
    public readonly string $createDate;
    private readonly ?array $history;
    private readonly ?Artikel $product;
    private readonly ?RMAReasonLangDomainObject $reason;
    private readonly ?string $productNo;
    private readonly ?string $orderStatus;
    private readonly ?string $seo;
    private readonly ?string $orderNo;
    private readonly ?string $orderDate;
    private readonly ?int $customerID;
    private readonly ?int $shippingAddressID;
    private readonly ?int $shippingNoteID;

    /**
     * @param int                                  $id
     * @param int                                  $rmaID
     * @param int|null                             $shippingNotePosID
     * @param int|null                             $orderID
     * @param int|null                             $orderPosID
     * @param int|null                             $productID
     * @param int|null                             $reasonID
     * @param string                               $name
     * @param int|null                             $variationProductID
     * @param string|null                          $variationName
     * @param string|null                          $variationValue
     * @param int|null                             $partListProductID
     * @param string|null                          $partListProductName
     * @param string|null                          $partListProductURL
     * @param string|null                          $partListProductNo
     * @param float                                $unitPriceNet
     * @param float                                $quantity
     * @param float                                $vat
     * @param string|null                          $unit
     * @param string|null                          $comment
     * @param string|null                          $status
     * @param string|null                          $createDate
     * @param array|null                           $history
     * @param Artikel|array|null                   $product
     * @param RMAReasonLangDomainObject|array|null $reason
     * @param string|null                          $productNo
     * @param string|null                          $orderStatus
     * @param string|null                          $seo
     * @param string|null                          $orderNo
     * @param string|null                          $orderDate
     * @param int|null                             $customerID
     * @param int|null                             $shippingAddressID
     * @param int|null                             $shippingNoteID
     * @param array                                $modifiedKeys
     */
    public function __construct(
        int $id = 0,
        int $rmaID = 0,
        ?int $shippingNotePosID = null,
        ?int $orderID = null,
        ?int $orderPosID = null,
        ?int $productID = null,
        ?int $reasonID = null,
        string $name = '',
        ?int $variationProductID = null,
        ?string $variationName = null,
        ?string $variationValue = null,
        ?int $partListProductID = null,
        ?string $partListProductName = null,
        ?string $partListProductURL = null,
        ?string $partListProductNo = null,
        float $unitPriceNet = 0.00,
        float $quantity = 0.00,
        float $vat = 0.00,
        ?string $unit = null,
        ?string $comment = null,
        ?string $status = null,
        ?string $createDate = null,
        ?array $history = null,
        Artikel|array|null $product = null,
        RMAReasonLangDomainObject|array|null $reason = null,
        ?string $productNo = null,
        ?string $orderStatus = null,
        ?string $seo = null,
        ?string $orderNo = null,
        ?string $orderDate = null,
        ?int $customerID = null,
        ?int $shippingAddressID = null,
        ?int $shippingNoteID = null,
        array $modifiedKeys = []
    ) {
        $this->id                  = $id;
        $this->rmaID               = $rmaID;
        $this->shippingNotePosID   = $shippingNotePosID;
        $this->orderID             = $orderID;
        $this->orderPosID          = $orderPosID;
        $this->productID           = $productID;
        $this->reasonID            = $reasonID;
        $this->name                = $name;
        $this->variationProductID  = $variationProductID;
        $this->variationName       = $variationName;
        $this->variationValue      = $variationValue;
        $this->partListProductID   = $partListProductID;
        $this->partListProductName = $partListProductName;
        $this->partListProductURL  = $partListProductURL;
        $this->partListProductNo   = $partListProductNo;
        $this->unitPriceNet        = $unitPriceNet;
        $this->quantity            = $quantity;
        $this->vat                 = $vat;
        $this->unit                = $unit;
        $this->comment             = $comment;
        $this->status              = $status;
        $this->createDate          = $createDate ?? \date('Y-m-d H:i:s');
        $this->history             = $history;

        if ($product instanceof Artikel) {
            $this->product = $product;
        } else {
            $this->product = null;
        }

        if ($reason instanceof RMAReasonLangDomainObject) {
            $this->reason = $reason;
        } else {
            $this->reason = null;
        }

        $this->productNo         = $productNo;
        $this->orderStatus       = $orderStatus;
        $this->seo               = $seo;
        $this->orderNo           = $orderNo;
        $this->orderDate         = $orderDate;
        $this->customerID        = $customerID;
        $this->shippingAddressID = $shippingAddressID;
        $this->shippingNoteID    = $shippingNoteID;

        parent::__construct($modifiedKeys);
    }

    /**
     * @return array|null
     */
    public function getHistory(): ?array
    {
        return $this->history;
    }

    /**
     * @return Artikel|null
     */
    public function getProduct(): ?Artikel
    {
        return $this->product;
    }

    /**
     * @return RMAReasonLangDomainObject|null
     */
    public function getReason(): ?RMAReasonLangDomainObject
    {
        return $this->reason;
    }

    /**
     * @return string
     */
    public function getProductNo(): string
    {
        return $this->productNo ?? '';
    }

    /**
     * @return string
     */
    public function getOrderStatus(): string
    {
        return $this->orderStatus ?? '';
    }

    /**
     * @return string
     */
    public function getSeo(): string
    {
        return $this->seo ?? '';
    }

    /**
     * @return string
     */
    public function getOrderNo(): string
    {
        return $this->orderNo ?? '';
    }

    /**
     * @param string $format
     * @return string
     */
    public function getOrderDate(string $format = 'Y-m-d H:i:s'): string
    {
        $result = $this->orderDate ?? '';
        if (!empty($result)) {
            $result = \date_format(\date_create($result), $format);
        }

        return $result;
    }

    /**
     * @return int
     */
    public function getCustomerID(): int
    {
        return $this->customerID ?? 0;
    }

    /**
     * @return int
     */
    public function getShippingAddressID(): int
    {
        return $this->shippingAddressID ?? 0;
    }

    /**
     * @return int
     */
    public function getShippingNoteID(): int
    {
        return $this->shippingNoteID ?? 0;
    }
}
