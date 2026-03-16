<template x-if="cart.length === 0">
    <div class="wps-text-sm wps-text-gray-500 wps-flex wps-items-center wps-gap-2">
        <span><?php echo wps_icon(['name' => 'cart', 'size' => 16]); ?></span>
        <span>Keranjang kosong.</span>
    </div>
    </template>
<template x-for="item in cart" :key="item.id + ':' + (item.options ? JSON.stringify(item.options) : '')">
    <div class="wps-flex wps-items-center wps-gap-2 wps-divider">
        <img :src="item.image ? item.image : '<?php echo esc_url(WP_STORE_URL . 'assets/frontend/img/noimg.webp'); ?>'" alt="" class="wps-img-40">
        <div style="flex: 1;">
            <div x-text="item.title" class="wps-text-sm wps-text-gray-900"></div>
            <template x-if="item.options && Object.keys(item.options).length">
                <div class="wps-text-xs wps-text-gray-500">
                    <span x-text="Object.entries(item.options).map(([k,v]) => k + ': ' + v).join(' • ')"></span>
                </div>
            </template>
            <div class="wps-text-xs wps-text-gray-500 wps-mb-1">
                <span x-text="formatPrice(item.price)"></span>
                <span> × </span>
                <span x-text="item.qty"></span>
                <span> = </span>
                <span class="wps-text-gray-900" x-text="formatPrice(item.subtotal)"></span>
            </div>
            <div class="wps-flex wps-items-center wps-gap-1">
                <button type="button" @click="decrement(item)" class="wps-btn wps-btn-secondary wps-btn-sm" style="padding: 2px 8px; font-size: 12px; line-height: 1; min-width: 24px; height: 22px;">-</button>
                <span x-text="item.qty" class="wps-badge wps-badge-sm" style="font-size: 12px; padding: 2px 6px; line-height: 1;"></span>
                <button type="button" @click="increment(item)" class="wps-btn wps-btn-secondary wps-btn-sm" style="padding: 2px 8px; font-size: 12px; line-height: 1; min-width: 24px; height: 22px;">+</button>
                <button type="button" @click="remove(item)" :disabled="loading && updatingKey === getItemKey(item)" class="wps-btn wps-btn-danger wps-btn-sm wps-ml-auto" :style="(loading && updatingKey === getItemKey(item)) ? 'opacity:.7; pointer-events:none;' : ''">
                    <template x-if="loading && updatingKey === getItemKey(item)">
                        <span><?php echo wps_icon(['name' => 'spinner', 'size' => 14]); ?></span>
                    </template>
                    <template x-if="!loading || updatingKey !== getItemKey(item)">
                        <span><?php echo wps_icon(['name' => 'close', 'size' => 14]); ?></span>
                    </template>
                </button>
            </div>
        </div>
    </div>
    </template>
