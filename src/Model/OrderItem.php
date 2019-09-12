<?php

namespace SilverShop\Model;

use SilverShop\Cart\ShoppingCartController;
use SilverShop\Forms\ShopQuantityField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\FieldType\DBCurrency;

/**
 * An order item is a product which has been added to an order,
 * ready for purchase. An order item is typically a product itself,
 * but also can include references to other information such as
 * product attributes like colour, size, or type.
 *
 * @property int $Quantity
 * @property DBCurrency $UnitPrice
 */
class OrderItem extends OrderAttribute
{
    private static $db = [
        'Quantity' => 'Float',
        'UnitPrice' => 'Currency',
    ];

    private static $casting = [
        'UnitPrice' => 'Currency',
        'Total' => 'Currency',
    ];

    private static $searchable_fields = [
        'OrderID' => [
            'title' => 'Order ID',
            'field' => TextField::class,
        ],
        'Title' => 'PartialMatchFilter',
        'TableTitle' => 'PartialMatchFilter',
        'CartTitle' => 'PartialMatchFilter',
        'UnitPrice',
        'Quantity',
        'Total',
    ];

    private static $summary_fields = [
        'Order.ID' => 'Order ID',
        'TableTitle' => 'Title',
        'UnitPrice' => 'Unit Price',
        'Quantity' => 'Quantity',
        'Total' => 'Total Price',
    ];

    private static $required_fields = array();

    /**
     * The ORM relationship to the buyable item
     *
     * @config
     * @var    string
     */
    private static $buyable_relationship = 'Product';

    private static $singular_name = 'Item';

    private static $plural_name = 'Items';

    private static $default_sort = '"Created" DESC';

    private static $table_name = 'SilverShop_OrderItem';

    /**
     * Get the buyable object related to this item.
     */
    public function Buyable()
    {
        return $this->{self::config()->buyable_relationship}();
    }

    /**
     * Get unit price for this item.
     * Fetches from db, or Buyable, based on order status.
     */
    public function UnitPrice()
    {
        if ($this->Order()->IsCart()) {
            $buyable = $this->Buyable();
            $unitprice = ($buyable) ? $buyable->sellingPrice() : $this->UnitPrice;
            $this->extend('updateUnitPrice', $unitprice);
            return $this->UnitPrice = $unitprice;
        }
        return $this->UnitPrice;
    }

    /**
     * Prevent unit price ever being below 0
     */
    public function setUnitPrice($val)
    {
        if ($val < 0) {
            $val = 0;
        }
        $this->setField('UnitPrice', $val);
    }

    /**
     * Prevent quantity being below 1.
     * 0 quantity means it should instead be deleted.
     *
     * @param int $val new quantity to set
     */
    public function setQuantity($val)
    {
        $val = $val < 1 ? 1 : $val;
        $this->setField('Quantity', $val);
    }

    /**
     * Get calculated total, or stored total
     * depending on whether the order is in cart
     */
    public function Total()
    {
        if ($this->Order()->IsCart()) { //always calculate total if order is in cart
            return $this->calculatetotal();
        }
        return $this->CalculatedTotal; //otherwise get value from database
    }

    /**
     * Calculates the total for this item.
     * Generally called by onBeforeWrite
     */
    protected function calculatetotal()
    {
        $total = $this->UnitPrice() * $this->Quantity;
        $this->extend('updateTotal', $total);
        $this->CalculatedTotal = $total;
        return $total;
    }

    /**
     * Intersects this item's required_fields with the data record.
     * This is used for uniquely adding items to the cart.
     */
    public function uniquedata()
    {
        $required = self::config()->required_fields; //TODO: also combine with all ancestors of this->class
        $unique = [];
        $hasOnes = $this->hasOne();
        //reduce record to only required fields
        if ($required) {
            foreach ($required as $field) {
                if ($hasOnes === $field || isset($hasOnes[$field])) {
                    $field = $field . 'ID'; //add ID to hasones
                }
                $unique[$field] = $this->$field;
            }
        }
        $this->extend('updateuniquedata',$unique);
        return $unique;
    }

    /**
     * Recalculate total before saving to database.
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->OrderID && $this->Order() && $this->Order()->isCart()) {
            $this->calculatetotal();
        }
    }

    /*
     * Event handler called when an order is fully paid for.
     */
    public function onPayment()
    {
        $this->extend('onPayment');
    }

    /**
     * Event handlier called for last time saving/processing,
     * before item permanently stored in database.
     * This should only be called when order is transformed from
     * Cart to Order, aka being 'placed'.
     */
    public function onPlacement()
    {
        $this->extend('onPlacement');
    }

    /**
     * Get the buyable image.
     * Also serves as a standardised placeholder for overriding in subclasses.
     */
    public function Image()
    {
        if ($this->Buyable()) {
            return $this->Buyable()->Image();
        }
    }

    /**
     * @return ShopQuantityField
     */
    public function QuantityField()
    {
        return ShopQuantityField::create($this);
    }

    /**
     * @return string
     */
    public function addLink()
    {
        $buyable = $this->Buyable();
        return $buyable ? ShoppingCartController::add_item_link($buyable, $this->uniquedata()) : '';
    }

    /**
     * @return string
     */
    public function removeLink()
    {
        $buyable = $this->Buyable();
        return $buyable ? ShoppingCartController::remove_item_link($buyable, $this->uniquedata()) : '';
    }

    /**
     * @return string
     */
    public function removeallLink()
    {
        $buyable = $this->Buyable();
        return $buyable ? ShoppingCartController::remove_all_item_link($buyable, $this->uniquedata()) : '';
    }

    /**
     * @return string
     */
    public function setquantityLink()
    {
        $buyable = $this->Buyable();
        return $buyable ? ShoppingCartController::set_quantity_item_link($buyable, $this->uniquedata()) : '';
    }
}
