<script setup>
// Kartu angka ringkas (KPI). variant mewarnai ikon: primary | success | warning | danger.
import Icon from '@/Components/ui/atoms/Icon.vue';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    hint: { type: String, default: null },
    icon: { type: String, default: null },
    variant: { type: String, default: 'primary' },
});
</script>

<template>
    <div class="stat-card">
        <span v-if="icon" class="stat-card-icon" :class="`stat-card-icon-${variant}`"><Icon :name="icon" /></span>
        <div class="min-w-0">
            <p class="stat-card-label">{{ label }}</p>
            <p class="stat-card-value">{{ value }}</p>
            <p v-if="hint" class="stat-card-hint">{{ hint }}</p>
        </div>
    </div>
</template>
