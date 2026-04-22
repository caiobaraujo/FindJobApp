<script setup>
const props = defineProps({
    status: {
        type: String,
        required: true,
    },
});

const classesByStatus = {
    wishlist: 'border-slate-400/20 bg-slate-400/10 text-slateglass-200',
    applied: 'border-slateglass-300/20 bg-slateglass-300/10 text-slateglass-200',
    interview: 'border-gold-400/20 bg-gold-400/10 text-gold-300',
    offer: 'border-gold-300/30 bg-gold-300/12 text-gold-200',
    rejected: 'border-red-400/20 bg-red-400/10 text-red-200',
};

function labelForStatus(status) {
    return status.charAt(0).toUpperCase() + status.slice(1);
}
</script>

<template>
    <span
        class="inline-flex rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.18em]"
        :class="classesByStatus[props.status] ?? classesByStatus.wishlist"
    >
        {{ labelForStatus(props.status) }}
    </span>
</template>
