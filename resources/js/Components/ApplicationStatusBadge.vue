<script setup>
const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const classesByStatus = {
    wishlist: 'border-slate-200 bg-slate-100 text-slate-700',
    applied: 'border-sky-200 bg-sky-100 text-sky-700',
    interview: 'border-amber-200 bg-amber-100 text-amber-800',
    offer: 'border-emerald-200 bg-emerald-100 text-emerald-800',
    rejected: 'border-rose-200 bg-rose-100 text-rose-800',
};

function labelForStatus(status) {
    return status.charAt(0).toUpperCase() + status.slice(1);
}
</script>

<template>
    <span
        class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
        :class="classesByStatus[props.status] ?? classesByStatus.wishlist"
    >
        {{ labelForStatus(props.status) }}
    </span>
</template>
