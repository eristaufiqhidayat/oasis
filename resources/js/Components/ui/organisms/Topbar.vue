<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Breadcrumb from '@/Components/ui/molecules/Breadcrumb.vue';
import Icon from '@/Components/ui/atoms/Icon.vue';
import Tooltip from '@/Components/ui/atoms/Tooltip.vue';

defineProps({ breadcrumbSlug: { type: String, default: null } });

const page = usePage();
const bisa = (izin) => {
    const daftar = page.props.auth?.user?.permissions ?? [];
    return daftar.includes('*') || daftar.includes(izin);
};

const notifOpen = ref(false);
const notifWrapper = ref(null);

function closeNotifOnOutsideClick(e) {
    if (notifWrapper.value && !notifWrapper.value.contains(e.target)) notifOpen.value = false;
}

onMounted(() => document.addEventListener('click', closeNotifOnOutsideClick));
onUnmounted(() => document.removeEventListener('click', closeNotifOnOutsideClick));
</script>

<template>
    <div class="topbar flex justify-between items-center gap-2 mb-3">
        <div>
            <h3 class="font-bold topbar-title">Oasis Canvassing</h3>
            <Breadcrumb :current-slug="breadcrumbSlug" />
        </div>
        <div class="flex items-center gap-2">
            <Tooltip v-if="bisa('trip.load')" label="Buat Muatan" align="end"><Link class="shortcut-btn" href="/muatan/baru" aria-label="Buat Muatan">
                    <Icon name="truck" />
                </Link></Tooltip>
            <Tooltip v-if="bisa('settlement.view')" label="Setoran Hari Ini" align="end"><Link class="shortcut-btn" href="/setoran" aria-label="Setoran Hari Ini">
                    <Icon name="calculator" />
                </Link></Tooltip>
            <div class="relative" ref="notifWrapper">
                <Tooltip label="Notifikasi Transaksi" align="end"><a class="notif-bell" href="#"
                        aria-label="Notifikasi Transaksi" @click.prevent="notifOpen = !notifOpen">
                        <Icon name="bell" />
                    </a></Tooltip>
                <div class="dropdown-menu" :class="{ hidden: !notifOpen }">
                    <Icon name="bell-off" class="me-1" /> Tidak ada notifikasi baru
                </div>
            </div>
        </div>
    </div>
</template>
