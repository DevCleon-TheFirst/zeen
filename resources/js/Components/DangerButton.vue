<template>
    <button
        class="inline-flex items-center rounded-xl border border-transparent px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 active:opacity-90 disabled:opacity-50"
        style="background:#991b1b;"
        @mouseover="$event.currentTarget.style.background='#b91c1c'"
        @mouseleave="$event.currentTarget.style.background='#991b1b'"
    >
        <slot />
    </button>
</template>
