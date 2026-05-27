<script setup lang="ts">
import { useCommandPalette } from '@/composables/useCommandPalette';
import { router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

interface Result {
    id: number;
    name: string;
    type: { value: string; label: string };
    path: string;
    thumb_url: string | null;
}

const { isOpen, open, close, toggle } = useCommandPalette();
const query = ref('');
const results = ref<Result[]>([]);
const active = ref(0);
const loading = ref(false);
const inputEl = ref<HTMLInputElement>();
const hasQuery = computed(() => query.value.trim().length > 0);
let debounce: number | undefined;
let seq = 0;

watch(isOpen, async (openNow) => {
    if (openNow) {
        query.value = '';
        results.value = [];
        active.value = 0;
        await nextTick();
        inputEl.value?.focus();
    }
});

watch(query, (q) => {
    window.clearTimeout(debounce);
    const term = q.trim();
    if (!term) {
        results.value = [];
        loading.value = false;
        return;
    }
    loading.value = true;
    debounce = window.setTimeout(async () => {
        const mine = ++seq;
        try {
            const res = await fetch(`/search?q=${encodeURIComponent(term)}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            if (mine === seq) {
                results.value = data.results ?? [];
                active.value = 0;
            }
        } finally {
            if (mine === seq) loading.value = false;
        }
    }, 200);
});

function goTo(result: Result) {
    close();
    router.visit(`/items/${result.id}`);
}

function viewAll() {
    const term = query.value.trim();
    close();
    router.visit(term ? `/search?q=${encodeURIComponent(term)}` : '/search');
}

function onKeydown(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        toggle();
        return;
    }
    if (!isOpen.value) return;
    if (e.key === 'Escape') {
        close();
    } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        active.value = Math.min(active.value + 1, results.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        active.value = Math.max(active.value - 1, 0);
    } else if (e.key === 'Enter' && results.value[active.value]) {
        e.preventDefault();
        goTo(results.value[active.value]);
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

defineExpose({ open });
</script>

<template>
    <div v-if="isOpen" class="cmdk-overlay" @click.self="close()">
        <div class="cmdk-panel" role="dialog" aria-label="Search items">
            <div class="cmdk-input">
                <Search :size="16" />
                <input ref="inputEl" v-model="query" type="text" :placeholder="$t('search.placeholder')" data-test="command-input" />
            </div>
            <div v-if="hasQuery" class="cmdk-results">
                <div v-if="loading && !results.length" class="cmdk-empty">{{ $t('search.command.searching') }}</div>
                <div v-else-if="!loading && !results.length" class="cmdk-empty">{{ $t('search.command.no_matches') }}</div>
                <button
                    v-for="(result, i) in results"
                    :key="result.id"
                    type="button"
                    :class="['cmdk-item', i === active ? 'active' : '']"
                    @click="goTo(result)"
                    @mousemove="active = i"
                >
                    <span class="cmdk-thumb">
                        <img v-if="result.thumb_url" :src="result.thumb_url" alt="" />
                    </span>
                    <span class="cmdk-text">
                        <span class="cmdk-name">{{ result.name }}</span>
                        <span class="cmdk-path">{{ result.path || $t('common.top_level') }}</span>
                    </span>
                    <span class="cmdk-type">{{ result.type.label }}</span>
                </button>
            </div>
            <button v-if="hasQuery" type="button" class="cmdk-footer" data-test="command-view-all" @click="viewAll()">
                {{ $t('search.command.see_all', { query: query.trim() }) }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.cmdk-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding-top: 12vh;
    z-index: 100;
}
.cmdk-panel {
    width: 100%;
    max-width: 560px;
    margin: 0 16px;
    background: var(--bg-elev);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    overflow: hidden;
}
.cmdk-input {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    color: var(--fg-muted);
}
.cmdk-input input {
    flex: 1;
    border: 0;
    background: transparent;
    outline: none;
    font-size: 15px;
    color: var(--fg);
}
.cmdk-results {
    border-top: 1px solid var(--border);
    max-height: 50vh;
    overflow-y: auto;
    padding: 6px;
}
.cmdk-empty {
    padding: 24px;
    text-align: center;
    color: var(--fg-muted);
    font-size: 13px;
}
.cmdk-item {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    padding: 8px 10px;
    border: 0;
    background: transparent;
    border-radius: var(--radius-sm);
    cursor: pointer;
    text-align: left;
}
.cmdk-item.active {
    background: var(--bg-sunken);
}
.cmdk-thumb {
    width: 32px;
    height: 32px;
    border-radius: var(--radius-sm);
    overflow: hidden;
    background: var(--bg-sunken);
    flex-shrink: 0;
    display: grid;
    place-items: center;
}
.cmdk-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.cmdk-text {
    display: flex;
    flex-direction: column;
    min-width: 0;
    flex: 1;
}
.cmdk-name {
    font-size: 13px;
    font-weight: 500;
    color: var(--fg);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cmdk-path {
    font-size: 11.5px;
    color: var(--fg-muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cmdk-type {
    font-size: 11px;
    color: var(--fg-subtle);
    flex-shrink: 0;
}
.cmdk-footer {
    width: 100%;
    border: 0;
    border-top: 1px solid var(--border);
    background: transparent;
    padding: 10px 16px;
    text-align: left;
    font-size: 12.5px;
    color: var(--fg-muted);
    cursor: pointer;
}
.cmdk-footer:hover {
    color: var(--fg);
    background: var(--bg-sunken);
}
</style>
