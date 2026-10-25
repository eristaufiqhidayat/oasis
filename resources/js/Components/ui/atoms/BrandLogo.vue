<script setup>
// Logo korporat Oasis. variant "full" = logo lengkap (tulisan OASIS + palem),
// "mark" = palem saja (dipakai di sidebar mode strip ikon dan favicon).
defineProps({
    variant: { type: String, default: 'full' },
});
</script>

<template>
    <span class="brand-logo" :class="`brand-logo-${variant}`">
        <img v-if="variant === 'mark'" src="/images/oasis-mark.png" alt="Oasis" />
        <img v-else src="/images/oasis-logo.png" alt="Oasis — Air Minum Dalam Kemasan" />
    </span>
</template>
