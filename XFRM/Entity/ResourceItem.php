<?php
/**
 * @license
 * Copyright 2018 TruongLuu. All Rights Reserved.
 */
 
namespace Truonglv\XFRMCustomized\XFRM\Entity;

use XF\Phrase;
use XF\Mvc\Entity\Structure;
use XF\Entity\PaymentProfile;
use Truonglv\XFRMCustomized\App;
use Truonglv\XFRMCustomized\Entity\Coupon;

/**
 * Class ResourceItem
 * @package Truonglv\XFRMCustomized\XFRM\Entity
 * @inheritdoc
 *
 * @property array $payment_profile_ids
 * @property float $renew_price
 * @property bool $is_purchased
 */
class ResourceItem extends XFCP_ResourceItem
{
    /**
     * @param mixed $message
     * @param mixed $key
     * @param mixed $specificError
     * @return void
     */
    public function error($message, $key = null, $specificError = true)
    {
        if ($key === 'currency'
            && $message instanceof Phrase
            && $message->getName() === 'xfrm_non_purchasable_resources_may_not_define_purchasable_components'
        ) {
            return;
        }

        parent::error($message, $key, $specificError);
    }

    /**
     * @return bool
     */
    public function isExternalPurchasable()
    {
        if (App::isDisabledCategory($this->resource_category_id)) {
            return parent::isExternalPurchasable();
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isExternalDownload()
    {
        if (App::isDisabledCategory($this->resource_category_id)) {
            return parent::isExternalDownload();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isXFRMCCommerceItem()
    {
        if (App::isDisabledCategory($this->resource_category_id)) {
            return false;
        }

        return $this->price > 0;
    }

    /**
     * @return bool
     */
    public function isDownloadable()
    {
        if (App::isDisabledCategory($this->resource_category_id)) {
            return parent::isDownloadable();
        }

        if (\XF::visitor()->user_id == $this->user_id) {
            return true;
        }

        if ($this->CurrentVersion !== null && $this->CurrentVersion->canDownload()) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    public function getExternalPurchaseUrl()
    {
        return $this->app()->router('public')->buildLink('canonical:resources/purchase', $this);
    }

    public function isRenewLicense(): bool
    {
        if (\XF::visitor()->user_id <= 0) {
            return false;
        }

        return $this->finder('Truonglv\XFRMCustomized:Purchase')
            ->where('user_id', \XF::visitor()->user_id)
            ->where('resource_id', $this->resource_id)
            ->where('new_purchase_id', 0)
            ->total() > 0;
    }

    /**
     * @param mixed $error
     * @return bool
     */
    public function canPurchase(&$error = null)
    {
        $visitor = \XF::visitor();
        if ($visitor->user_id <= 0 || $visitor->user_id === $this->user_id) {
            return false;
        }

        return $this->price > 0;
    }

    /**
     * @param mixed $error
     * @return bool
     */
    public function canTransferLicense(&$error = null): bool
    {
        $visitor = \XF::visitor();
        if ($visitor->user_id <= 0) {
            return false;
        }

        if ($this->hasPermission('editAny')) {
            return true;
        }

        return $this->is_purchased;
    }

    public function getRenewPrice(): float
    {
        return $this->renew_price + App::getFee($this->renew_price);
    }

    public function getXFRMCPriceForProfile(PaymentProfile $paymentProfile, Coupon $coupon = null): float
    {
        if ($paymentProfile->provider_id === 'paypal') {
            $price = $this->price + App::getFee($this->price);
        } else {
            $price = $this->price;
        }

        return $coupon === null ? $price : $coupon->calcPrice($price);
    }

    /**
     * @return array
     */
    public function getPaymentProfileIds()
    {
        $ids = $this->get('payment_profile_ids_');
        $ids = array_map('intval', $ids);

        return $ids;
    }

    /**
     * @param mixed $error
     * @return bool
     */
    public function canAddBuyer(&$error = null)
    {
        return $this->hasPermission('xfrmc_addBuyer');
    }

    /**
     * @param mixed $error
     * @return bool
     */
    public function canViewHistory(&$error = null)
    {
        $visitor = \XF::visitor();
        if ($visitor->user_id <= 0) {
            return false;
        }

        if ($this->hasPermission('xfrmc_viewHistoryAny')) {
            return true;
        }

        return $visitor->user_id === $this->user_id;
    }

    public function setIsPurchased(bool $flag): void
    {
        $this->_getterCache['is_purchased'] = $flag;
    }

    public function getIsPurchased(): bool
    {
        if (array_key_exists('is_purchased', $this->_getterCache)) {
            return $this->_getterCache['is_purchased'];
        }

        return App::purchaseRepo()->isPurchased($this, \XF::visitor());
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns += [
            'payment_profile_ids' => ['type' => self::LIST_COMMA, 'default' => []],
            'renew_price' => ['type' => self::FLOAT, 'default' => 0]
        ];

        $structure->getters['payment_profile_ids'] = true;
        $structure->getters['external_purchase_url'] = true;
        $structure->getters['is_purchased'] = true;

        return $structure;
    }
}
